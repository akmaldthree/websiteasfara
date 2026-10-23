<?php

namespace App\Services;

use App\Models\BundleComponent;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CustomQuote;
use App\Models\Order;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private PricingService $pricing, private CustomProductService $customProducts, private LoyaltyService $loyalty) {}

    public function lines(array $cart, bool $lock = false, ?User $customer = null, array $customizations = []): array
    {
        $this->check(count($cart) > 0 && count($cart) <= 50, 'Keranjang kosong atau terlalu banyak varian.');
        $group = $this->pricing->groupFor($customer);
        $query = Variant::with([
            'series.category', 'measurementTemplate', 'bundleComponents.componentVariant.series',
            'prices' => fn ($query) => $query->when($group, fn ($query) => $query->where('price_group_id', $group->id))->where('active', true),
        ])->whereIn('id', array_keys($cart))->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $variants = $query->get();
        $lines = [];
        $this->check($variants->count() === count($cart), 'Ada varian yang sudah tidak tersedia.');
        foreach ($variants as $v) {
            $qty = filter_var($cart[$v->id], FILTER_VALIDATE_INT);
            $this->check($qty !== false && $qty >= 1 && $qty <= 100, 'Jumlah harus antara 1 dan 100.');
            $this->check($v->active && $v->series->active && $v->series->category->active, "{$v->sku} tidak aktif.");
            $this->check(in_array($v->fulfillment, ['ready_stock', 'pre_order', 'custom_made', 'bundle']), 'Metode pemenuhan tidak tersedia.');
            $bundle = [];
            $unitWeight = $v->weight;
            $codEligible = $v->fulfillment === 'ready_stock';
            if ($v->fulfillment === 'bundle') {
                $this->check($v->bundleComponents->isNotEmpty(), "Komponen bundle {$v->sku} belum dikonfigurasi.");
                foreach ($v->bundleComponents as $component) {
                    $componentVariant = $component->componentVariant;
                    $this->check($componentVariant->active && $componentVariant->series->active && $componentVariant->fulfillment !== 'bundle', "Komponen {$componentVariant->sku} tidak tersedia.");
                    $bundle[] = ['variant_id' => $componentVariant->id, 'sku' => $componentVariant->sku, 'name' => $componentVariant->series->name, 'quantity' => $component->quantity];
                }
                $unitWeight = $v->bundleComponents->sum(fn ($component) => $component->componentVariant->weight * $component->quantity);
                $codEligible = $v->bundleComponents->every(fn ($component) => $component->componentVariant->fulfillment === 'ready_stock');
            }
            $customization = null;
            if ($v->fulfillment === 'custom_made') {
                $this->check($qty === 1, 'Setiap ukuran custom harus dibuat sebagai item terpisah.');
                $customization = $customizations[$v->id] ?? null;
                $this->check(is_array($customization), "Lengkapi dan setujui ukuran custom untuk {$v->sku}.");
                if (! empty($customization['quote_id'])) {
                    $quote = CustomQuote::whereKey($customization['quote_id'])->when($lock, fn ($query) => $query->lockForUpdate())->first();
                    $this->check($customer && $quote && $quote->user_id === $customer->id && $quote->variant_id === $v->id && $quote->status === 'accepted' && $quote->order_id === null, 'Penawaran custom tidak valid atau sudah digunakan.');
                    $customization['surcharge'] = $quote->surcharge;
                    $customization['lead_time'] = $quote->lead_time;
                    $customization['measurements'] = $quote->measurements;
                    $customization['template'] = $quote->template_snapshot;
                } else {
                    $this->check($v->measurementTemplate?->active === true && $v->measurementTemplate->pricing_mode === 'fixed', 'Template custom berubah. Isi ulang ukuran sebelum checkout.');
                    $approvedAt = $customization['approved_at'] ?? null;
                    $referencePath = $customization['reference_path'] ?? null;
                    $customization = $this->customProducts->fixedCustomization($v, $customization['measurements'] ?? [], $referencePath);
                    $customization['approved_at'] = $approvedAt;
                }
            }
            $this->check($v->available >= $qty, "Stok / kuota {$v->sku} tidak mencukupi.");
            $price = $this->pricing->resolveForGroup($v, $qty, $group);
            $customCost = (int) ($customization['surcharge'] ?? 0);
            $lines[] = [
                'variant' => $v,
                'quantity' => $qty,
                'unit_price' => $price['price'],
                'price_tier_minimum' => $price['minimum_quantity'],
                'price_group' => $group,
                'customization' => $customization,
                'custom_cost' => $customCost,
                'bundle' => $bundle,
                'unit_weight' => $unitWeight,
                'cod_eligible' => $codEligible,
                'base_total' => $qty * $price['price'],
                'total' => $qty * $price['price'] + $customCost,
            ];
        }

        return $lines;
    }

    public function quote(array $cart, array $input, bool $lock = false, ?User $customer = null, array $customizations = []): array
    {
        $lines = $this->lines($cart, $lock, $customer, $customizations);
        $subtotal = array_sum(array_column($lines, 'base_total'));
        $customCost = array_sum(array_column($lines, 'custom_cost'));
        $merchandiseTotal = $subtotal + $customCost;
        $group = $lines[0]['price_group'];
        $businessAccount = $group ? $customer?->businessAccount()->with('salesUser')->first() : null;
        if ($group) {
            $quantity = array_sum(array_column($lines, 'quantity'));
            $this->check($quantity >= $group->minimum_order_quantity, "Minimum grup {$group->name} adalah {$group->minimum_order_quantity} item per pesanan.");
            $this->check($merchandiseTotal >= $group->minimum_order_amount, "Minimum nilai pesanan grup {$group->name} adalah Rp ".number_format($group->minimum_order_amount, 0, ',', '.').'.');
            foreach ($group->orderRules()->where('active', true)->get() as $rule) {
                $matched = array_filter($lines, fn ($line) => match ($rule->target_type) {
                    'category' => $line['variant']->series->category_id === $rule->target_id,
                    'series' => $line['variant']->series_id === $rule->target_id,
                    'variant' => $line['variant']->id === $rule->target_id,
                    default => false,
                });
                $quantity = array_sum(array_column($matched, 'quantity'));
                $amount = array_sum(array_column($matched, 'total'));
                $this->check($quantity >= $rule->minimum_quantity && $amount >= $rule->minimum_amount, "Minimum pembelian bertarget untuk grup {$group->name} belum terpenuhi.");
            }
        }
        $weight = array_sum(array_map(fn ($l) => $l['unit_weight'] * $l['quantity'], $lines));
        $rate = ShippingRate::where('active', true)->find($input['shipping_rate_id'] ?? 0);
        $this->check($rate !== null, 'Pilih layanan pengiriman yang tersedia.');
        foreach (['province', 'city'] as $field) {
            $this->check($rate->$field === '*' || mb_strtolower(trim($rate->$field)) === mb_strtolower(trim($input[$field] ?? '')), 'Tarif pengiriman tidak cocok dengan wilayah tujuan.');
        }
        $shipping = $rate->first_kg + max(0, (int) ceil($weight / 1000) - 1) * $rate->extra_kg;
        $fee = 0;
        if (($input['payment_method'] ?? '') === 'cod') {
            $this->check(Setting::get('cod_enabled', false) && $rate->cod, 'COD belum tersedia untuk layanan ini.');
            $this->check($merchandiseTotal >= (int) Setting::get('cod_min', 0) && $merchandiseTotal <= (int) Setting::get('cod_max', 1000000), 'Nilai pesanan di luar batas COD.');
            $segment = ! $customer ? 'guest' : ($group ? 'business' : 'customer');
            $this->check(in_array($segment, Setting::get('cod_allowed_segments', ['guest', 'customer', 'business']), true), 'COD tidak tersedia untuk kelompok pelanggan ini.');
            $phone = preg_replace('/\D+/', '', $input['phone'] ?? '');
            $this->check(! in_array($phone, Setting::get('cod_blocked_phones', []), true), 'COD tidak tersedia untuk nomor ini. Pilih transfer bank.');
            $excludedSkus = Setting::get('cod_excluded_skus', []);
            $excludedCategories = Setting::get('cod_excluded_categories', []);
            foreach ($lines as $l) {
                $this->check($l['cod_eligible'], 'COD hanya untuk ready stock.');
                $this->check(! in_array(strtoupper($l['variant']->sku), $excludedSkus, true), 'Salah satu SKU tidak memenuhi aturan COD.');
                $this->check(! in_array(strtolower($l['variant']->series->category->slug), $excludedCategories, true), 'Salah satu kategori tidak memenuhi aturan COD.');
            }
            $fee = min((int) Setting::get('cod_fee_max', 100000), (int) Setting::get('cod_fee', 0) + (int) ceil($merchandiseTotal * (int) Setting::get('cod_percent', 0) / 100));
        } elseif (($input['payment_method'] ?? '') === 'transfer') {
            $this->check(count(Setting::get('bank_accounts', [])) > 0, 'Rekening transfer belum dikonfigurasi.');
        } else {
            $this->check(($input['payment_method'] ?? '') === 'credit' && $businessAccount && $businessAccount->credit_terms_days > 0 && $businessAccount->credit_limit > 0, 'Termin kredit tidak tersedia untuk akun ini.');
        }
        $discount = 0;
        $shippingDiscount = 0;
        $coupons = collect();
        $couponBenefits = [];
        $remainingByVariant = collect($lines)->mapWithKeys(fn ($line) => [$line['variant']->id => $line['total']])->all();
        if (! empty($input['coupon'])) {
            $codes = collect(preg_split('/[,\s]+/', strtoupper(trim($input['coupon']))))->filter()->unique()->values();
            $this->check($codes->count() <= 5, 'Maksimal lima voucher dapat digunakan sekaligus.');
            $query = Coupon::whereIn('code', $codes)->orderByDesc('priority')->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            $coupons = $query->get();
            $this->check($coupons->count() === $codes->count(), 'Satu atau lebih voucher tidak ditemukan.');
            $this->check($coupons->count() <= 1 || $coupons->every->combinable, 'Voucher ini tidak dapat digabungkan.');
            foreach ($coupons as $coupon) {
                $this->check($coupon->active && now()->between($coupon->starts_at, $coupon->ends_at) && $coupon->used < $coupon->usage_limit, "Voucher {$coupon->code} tidak aktif atau kuotanya habis.");
                $segmentAllowed = $coupon->segment === 'all' || ($coupon->segment === 'guest' && ! $customer) || ($coupon->segment === 'customer' && $customer && ! $group) || ($coupon->segment === 'business' && $group);
                $this->check($segmentAllowed && (! $coupon->price_group_id || $group?->id === $coupon->price_group_id), "Voucher {$coupon->code} tidak berlaku untuk akun ini.");
                if ($coupon->per_user_limit) {
                    $this->check($customer && CouponRedemption::where('coupon_id', $coupon->id)->where('user_id', $customer->id)->count() < $coupon->per_user_limit, "Batas pemakaian voucher {$coupon->code} sudah tercapai.");
                }
                $eligibleLines = array_filter($lines, function ($line) use ($coupon) {
                    $variant = $line['variant'];

                    return $coupon->target_type === 'all'
                        || ($coupon->target_type === 'variant' && $variant->id === $coupon->target_id)
                        || ($coupon->target_type === 'series' && $variant->series_id === $coupon->target_id)
                        || ($coupon->target_type === 'category' && $variant->series->category_id === $coupon->target_id);
                });
                $eligible = array_sum(array_map(fn ($line) => $remainingByVariant[$line['variant']->id], $eligibleLines));
                $this->check($eligible >= $coupon->minimum && $eligible > 0, "Minimum belanja atau target voucher {$coupon->code} tidak terpenuhi.");
                $benefit = $coupon->type === 'percent' ? (int) floor($eligible * $coupon->value / 100) : $coupon->value;
                if ($coupon->maximum !== null) {
                    $benefit = min($benefit, $coupon->maximum);
                }
                if ($coupon->type === 'shipping') {
                    $applied = min($shipping - $shippingDiscount, $benefit);
                    $shippingDiscount += $applied;
                } else {
                    $applied = min($eligible, $benefit);
                    $discount = min($merchandiseTotal, $discount + $applied);
                    $left = $applied;
                    foreach ($eligibleLines as $line) {
                        $variantId = $line['variant']->id;
                        $lineDiscount = min($remainingByVariant[$variantId], $left);
                        $remainingByVariant[$variantId] -= $lineDiscount;
                        $left -= $lineDiscount;
                        if ($left === 0) {
                            break;
                        }
                    }
                }
                $couponBenefits[$coupon->id] = $applied;
            }
        }

        $loyalty = ['points' => 0, 'discount' => 0];
        $requestedPoints = max(0, (int) ($input['loyalty_points'] ?? 0));
        if ($requestedPoints > 0) {
            $this->check($customer !== null, 'Masuk ke akun untuk menggunakan poin loyalty.');
            $loyalty = $this->loyalty->redemption($customer, $requestedPoints, max(0, $merchandiseTotal - $discount));
        }

        $total = $merchandiseTotal + $shipping + $fee - $discount - $shippingDiscount - $loyalty['discount'];
        if (($input['payment_method'] ?? '') === 'credit') {
            $outstanding = Order::where('user_id', $customer->id)->where('payment_method', 'credit')->whereNull('paid_at')->where('status', '!=', 'cancelled')->sum('total');
            $this->check($outstanding + $total <= $businessAccount->credit_limit, 'Limit kredit tidak mencukupi untuk pesanan ini.');
        }

        return ['lines' => $lines, 'price_group' => $group, 'business_account' => $businessAccount, 'subtotal' => $subtotal, 'custom_cost' => $customCost, 'weight' => $weight, 'rate' => $rate, 'shipping_cost' => $shipping, 'discount' => $discount, 'shipping_discount' => $shippingDiscount, 'cod_fee' => $fee, 'loyalty_points' => $loyalty['points'], 'loyalty_discount' => $loyalty['discount'], 'total' => $total, 'coupons' => $coupons, 'coupon_benefits' => $couponBenefits];
    }

    public function fingerprint(array $quote): string
    {
        return hash('sha256', json_encode([
            'items' => array_map(fn ($line) => [$line['variant']->id, $line['variant']->sku, $line['quantity'], $line['unit_price'], $line['price_tier_minimum'], $line['custom_cost'], $line['customization'], $line['bundle'], $line['unit_weight'], $line['variant']->fulfillment, $line['variant']->lead_time], $quote['lines']),
            'price_group' => $quote['price_group'] ? [$quote['price_group']->id, $quote['price_group']->name, $quote['price_group']->business_type, $quote['price_group']->minimum_order_quantity, $quote['price_group']->minimum_order_amount, $quote['price_group']->discount_percent, $quote['business_account']->credit_terms_days, $quote['business_account']->credit_limit, $quote['business_account']->sales_user_id, $quote['business_account']->commission_percent] : null,
            'shipping' => [$quote['rate']->id, $quote['rate']->courier, $quote['rate']->service, $quote['rate']->estimate],
            'coupons' => $quote['coupons']->map(fn ($coupon) => [$coupon->id, $coupon->code, $coupon->type, $coupon->value, $coupon->maximum, $coupon->target_type, $coupon->target_id, $coupon->segment, $coupon->price_group_id, $coupon->per_user_limit, $coupon->combinable, $coupon->priority, $quote['coupon_benefits'][$coupon->id]])->values()->all(),
            'amounts' => array_intersect_key($quote, array_flip(['subtotal', 'custom_cost', 'shipping_cost', 'discount', 'shipping_discount', 'cod_fee', 'loyalty_points', 'loyalty_discount', 'total'])),
        ], JSON_THROW_ON_ERROR));
    }

    public function place(array $cart, array $input, string $key, ?int $userId = null, ?string $expectedQuote = null, array $customizations = []): Order
    {
        return DB::transaction(function () use ($cart, $input, $key, $userId, $expectedQuote, $customizations) {
            // Serialize retries on the same cart before checking the unique checkout key.
            $componentIds = BundleComponent::whereIn('bundle_variant_id', array_keys($cart))->pluck('component_variant_id')->all();
            $lockIds = array_values(array_unique(array_merge(array_keys($cart), $componentIds)));
            sort($lockIds);
            Variant::whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get();
            if ($existing = Order::where('checkout_key', $key)->first()) {
                return $existing;
            }
            $this->check(Setting::get('checkout_enabled', false), 'Checkout belum dibuka. Silakan hubungi Dthree.');
            $customer = $userId ? User::whereKey($userId)->lockForUpdate()->first() : null;
            $q = $this->quote($cart, $input, true, $customer, $customizations);
            $this->check($expectedQuote === null || hash_equals($expectedQuote, $this->fingerprint($q)), 'Harga atau pengiriman berubah. Kembali ke checkout untuk meninjau total terbaru sebelum memesan.');
            $order = Order::create([
                'number' => 'D3-'.now()->format('ymd').'-'.strtoupper(Str::random(8)), 'checkout_key' => $key, 'user_id' => $userId,
                'price_group_id' => $q['price_group']?->id,
                'business_snapshot' => $q['price_group'] ? ['name' => $q['price_group']->name, 'slug' => $q['price_group']->slug, 'business_type' => $q['price_group']->business_type, 'minimum_order_quantity' => $q['price_group']->minimum_order_quantity, 'minimum_order_amount' => $q['price_group']->minimum_order_amount, 'discount_percent' => $q['price_group']->discount_percent, 'business_account_id' => $q['business_account']->id, 'credit_terms_days' => $q['business_account']->credit_terms_days, 'credit_limit' => $q['business_account']->credit_limit, 'sales_user_id' => $q['business_account']->sales_user_id, 'sales_user_name' => $q['business_account']->salesUser?->name, 'commission_percent' => $q['business_account']->commission_percent] : null,
                'dropship_snapshot' => $q['price_group']?->business_type === 'dropshipper' && ! empty($input['sender_name']) ? ['name' => $input['sender_name'], 'phone' => $input['sender_phone'] ?? null] : null,
                'name' => $input['name'], 'phone' => $input['phone'], 'email' => $input['email'] ?? null,
                'address' => array_intersect_key($input, array_flip(['province', 'city', 'district', 'postal_code', 'detail'])),
                'payment_method' => $input['payment_method'], 'subtotal' => $q['subtotal'], 'custom_cost' => $q['custom_cost'], 'shipping_cost' => $q['shipping_cost'],
                'discount' => $q['discount'], 'loyalty_points_redeemed' => $q['loyalty_points'], 'loyalty_discount' => $q['loyalty_discount'], 'shipping_discount' => $q['shipping_discount'], 'cod_fee' => $q['cod_fee'], 'total' => $q['total'],
                'shipping' => ['courier' => $q['rate']->courier, 'service' => $q['rate']->service, 'estimate' => $q['rate']->estimate, 'source' => 'manual', 'origin' => 'Tasikmalaya', 'weight' => $q['weight']],
                'bank_accounts' => Setting::get('bank_accounts', []), 'coupon_id' => $q['coupons']->first()?->id, 'coupon_code' => $q['coupons']->isEmpty() ? null : $q['coupons']->pluck('code')->implode(','),
                'coupon_snapshot' => $q['coupons']->map(fn ($coupon) => ['id' => $coupon->id, 'code' => $coupon->code, 'type' => $coupon->type, 'value' => $coupon->value, 'benefit' => $q['coupon_benefits'][$coupon->id], 'target_type' => $coupon->target_type, 'target_id' => $coupon->target_id])->values()->all(),
                'notes' => $input['notes'] ?? null,
                'expires_at' => $input['payment_method'] === 'credit' ? now()->addDays($q['business_account']->credit_terms_days) : now()->addHours((int) Setting::get($input['payment_method'] === 'cod' ? 'cod_hours' : 'payment_hours', 24)),
                'credit_due_at' => $input['payment_method'] === 'credit' ? now()->addDays($q['business_account']->credit_terms_days) : null,
            ]);
            $this->loyalty->redeem($order);
            foreach ($q['lines'] as $l) {
                $v = $l['variant'];
                $qty = $l['quantity'];
                if ($v->fulfillment !== 'bundle') {
                    $updated = Variant::whereKey($v->id)->whereRaw('stock - reserved >= ?', [$qty])->increment('reserved', $qty);
                    $this->check($updated === 1, "Stok {$v->sku} sudah terambil. Perbarui keranjang.");
                }
                $item = $order->items()->create(['variant_id' => $v->id, 'sku' => $v->sku, 'name' => $v->series->name, 'attributes' => ['type' => $v->product_type, 'color' => $v->color, 'size' => $v->size, 'price_group' => $q['price_group']?->name, 'price_tier_minimum' => $l['price_tier_minimum'], 'custom' => $l['customization']], 'fulfillment' => $v->fulfillment, 'lead_time' => $l['customization']['lead_time'] ?? $v->lead_time, 'weight' => $l['unit_weight'], 'price' => $l['unit_price'], 'custom_cost' => $l['custom_cost'], 'production_status' => $v->fulfillment === 'custom_made' ? 'reviewing' : null, 'quantity' => $qty, 'total' => $l['total']]);
                if ($v->fulfillment === 'bundle') {
                    foreach ($l['bundle'] as $component) {
                        $required = $component['quantity'] * $qty;
                        $updated = Variant::whereKey($component['variant_id'])->whereRaw('stock - reserved >= ?', [$required])->increment('reserved', $required);
                        $this->check($updated === 1, "Stok komponen {$component['sku']} sudah terambil. Perbarui keranjang.");
                        $item->components()->create(array_merge($component, ['quantity' => $required]));
                        StockMovement::create(['variant_id' => $component['variant_id'], 'order_id' => $order->id, 'user_id' => $userId, 'stock_delta' => 0, 'reserved_delta' => $required, 'reason' => 'Reservasi komponen bundle '.$v->sku]);
                    }
                }
                if ($v->fulfillment === 'custom_made') {
                    $item->productionMilestones()->create(['status' => 'reviewing', 'note' => 'Ukuran disetujui pelanggan saat checkout.']);
                    if ($quoteId = $l['customization']['quote_id'] ?? null) {
                        CustomQuote::whereKey($quoteId)->update(['status' => 'ordered', 'order_id' => $order->id]);
                    }
                }
                if ($v->fulfillment !== 'bundle') {
                    StockMovement::create(['variant_id' => $v->id, 'order_id' => $order->id, 'user_id' => $userId, 'stock_delta' => 0, 'reserved_delta' => $qty, 'reason' => 'Reservasi checkout']);
                }
            }
            foreach ($q['coupons'] as $coupon) {
                $coupon->increment('used');
                CouponRedemption::create(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'user_id' => $userId, 'discount' => $q['coupon_benefits'][$coupon->id]]);
            }
            Audit::record('order.created', 'order:'.$order->id, null, ['number' => $order->number, 'total' => $order->total]);
            OrderCommunication::record($order, 'created', 'created', 'Pesanan berhasil dibuat. Silakan lihat instruksi pembayaran dan batas waktunya pada halaman pesanan.');

            return $order;
        }, 3);
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['checkout' => $message]);
        }
    }
}
