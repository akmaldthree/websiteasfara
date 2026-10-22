<?php

namespace App\Services;

use App\Models\Coupon;
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
    public function __construct(private PricingService $pricing) {}

    public function lines(array $cart, bool $lock = false, ?User $customer = null): array
    {
        $this->check(count($cart) > 0 && count($cart) <= 50, 'Keranjang kosong atau terlalu banyak varian.');
        $group = $this->pricing->groupFor($customer);
        $query = Variant::with([
            'series.category',
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
            $this->check(in_array($v->fulfillment, ['ready_stock', 'pre_order']), 'Custom-made belum tersedia di checkout.');
            $this->check($v->available >= $qty, "Stok / kuota {$v->sku} tidak mencukupi.");
            $price = $this->pricing->resolveForGroup($v, $qty, $group);
            $lines[] = [
                'variant' => $v,
                'quantity' => $qty,
                'unit_price' => $price['price'],
                'price_tier_minimum' => $price['minimum_quantity'],
                'price_group' => $group,
                'total' => $qty * $price['price'],
            ];
        }

        return $lines;
    }

    public function quote(array $cart, array $input, bool $lock = false, ?User $customer = null): array
    {
        $lines = $this->lines($cart, $lock, $customer);
        $subtotal = array_sum(array_column($lines, 'total'));
        $group = $lines[0]['price_group'];
        if ($group) {
            $quantity = array_sum(array_column($lines, 'quantity'));
            $this->check($quantity >= $group->minimum_order_quantity, "Minimum grup {$group->name} adalah {$group->minimum_order_quantity} item per pesanan.");
            $this->check($subtotal >= $group->minimum_order_amount, "Minimum nilai pesanan grup {$group->name} adalah Rp ".number_format($group->minimum_order_amount, 0, ',', '.').'.');
        }
        $weight = array_sum(array_map(fn ($l) => $l['variant']->weight * $l['quantity'], $lines));
        $rate = ShippingRate::where('active', true)->find($input['shipping_rate_id'] ?? 0);
        $this->check($rate !== null, 'Pilih layanan pengiriman yang tersedia.');
        foreach (['province', 'city'] as $field) {
            $this->check($rate->$field === '*' || mb_strtolower(trim($rate->$field)) === mb_strtolower(trim($input[$field] ?? '')), 'Tarif pengiriman tidak cocok dengan wilayah tujuan.');
        }
        $shipping = $rate->first_kg + max(0, (int) ceil($weight / 1000) - 1) * $rate->extra_kg;
        $fee = 0;
        if (($input['payment_method'] ?? '') === 'cod') {
            $this->check(Setting::get('cod_enabled', false) && $rate->cod, 'COD belum tersedia untuk layanan ini.');
            $this->check($subtotal >= (int) Setting::get('cod_min', 0) && $subtotal <= (int) Setting::get('cod_max', 1000000), 'Nilai pesanan di luar batas COD.');
            foreach ($lines as $l) {
                $this->check($l['variant']->fulfillment === 'ready_stock', 'COD hanya untuk ready stock.');
            }
            $fee = min((int) Setting::get('cod_fee_max', 100000), (int) Setting::get('cod_fee', 0) + (int) ceil($subtotal * (int) Setting::get('cod_percent', 0) / 100));
        } else {
            $this->check(($input['payment_method'] ?? '') === 'transfer', 'Metode pembayaran tidak valid.');
            $this->check(count(Setting::get('bank_accounts', [])) > 0, 'Rekening transfer belum dikonfigurasi.');
        }
        $discount = 0;
        $shippingDiscount = 0;
        $coupon = null;
        if (! empty($input['coupon'])) {
            $query = Coupon::where('code', strtoupper(trim($input['coupon'])));
            if ($lock) {
                $query->lockForUpdate();
            }
            $coupon = $query->first();
            $this->check($coupon && $coupon->active && now()->between($coupon->starts_at, $coupon->ends_at), 'Voucher tidak ditemukan atau di luar masa berlaku.');
            $this->check($subtotal >= $coupon->minimum && $coupon->used < $coupon->usage_limit, 'Minimum belanja atau kuota voucher tidak terpenuhi.');
            $benefit = $coupon->type === 'percent' ? (int) floor($subtotal * $coupon->value / 100) : $coupon->value;
            if ($coupon->maximum !== null) {
                $benefit = min($benefit, $coupon->maximum);
            }
            if ($coupon->type === 'shipping') {
                $shippingDiscount = min($shipping, $benefit);
            } else {
                $discount = min($subtotal, $benefit);
            }
        }

        return ['lines' => $lines, 'price_group' => $group, 'subtotal' => $subtotal, 'weight' => $weight, 'rate' => $rate, 'shipping_cost' => $shipping, 'discount' => $discount, 'shipping_discount' => $shippingDiscount, 'cod_fee' => $fee, 'total' => $subtotal + $shipping + $fee - $discount - $shippingDiscount, 'coupon' => $coupon];
    }

    public function fingerprint(array $quote): string
    {
        return hash('sha256', json_encode([
            'items' => array_map(fn ($line) => [$line['variant']->id, $line['variant']->sku, $line['quantity'], $line['unit_price'], $line['price_tier_minimum'], $line['variant']->weight, $line['variant']->fulfillment, $line['variant']->lead_time], $quote['lines']),
            'price_group' => $quote['price_group'] ? [$quote['price_group']->id, $quote['price_group']->name, $quote['price_group']->business_type, $quote['price_group']->minimum_order_quantity, $quote['price_group']->minimum_order_amount] : null,
            'shipping' => [$quote['rate']->id, $quote['rate']->courier, $quote['rate']->service, $quote['rate']->estimate],
            'amounts' => array_intersect_key($quote, array_flip(['subtotal', 'shipping_cost', 'discount', 'shipping_discount', 'cod_fee', 'total'])),
        ], JSON_THROW_ON_ERROR));
    }

    public function place(array $cart, array $input, string $key, ?int $userId = null, ?string $expectedQuote = null): Order
    {
        return DB::transaction(function () use ($cart, $input, $key, $userId, $expectedQuote) {
            // Serialize retries on the same cart before checking the unique checkout key.
            Variant::whereIn('id', array_keys($cart))->orderBy('id')->lockForUpdate()->get();
            if ($existing = Order::where('checkout_key', $key)->first()) {
                return $existing;
            }
            $this->check(Setting::get('checkout_enabled', false), 'Checkout belum dibuka. Silakan hubungi Dthree.');
            $customer = $userId ? User::find($userId) : null;
            $q = $this->quote($cart, $input, true, $customer);
            $this->check($expectedQuote === null || hash_equals($expectedQuote, $this->fingerprint($q)), 'Harga atau pengiriman berubah. Kembali ke checkout untuk meninjau total terbaru sebelum memesan.');
            $order = Order::create([
                'number' => 'D3-'.now()->format('ymd').'-'.strtoupper(Str::random(8)), 'checkout_key' => $key, 'user_id' => $userId,
                'price_group_id' => $q['price_group']?->id,
                'business_snapshot' => $q['price_group'] ? ['name' => $q['price_group']->name, 'slug' => $q['price_group']->slug, 'business_type' => $q['price_group']->business_type, 'minimum_order_quantity' => $q['price_group']->minimum_order_quantity, 'minimum_order_amount' => $q['price_group']->minimum_order_amount] : null,
                'name' => $input['name'], 'phone' => $input['phone'], 'email' => $input['email'] ?? null,
                'address' => array_intersect_key($input, array_flip(['province', 'city', 'district', 'postal_code', 'detail'])),
                'payment_method' => $input['payment_method'], 'subtotal' => $q['subtotal'], 'shipping_cost' => $q['shipping_cost'],
                'discount' => $q['discount'], 'shipping_discount' => $q['shipping_discount'], 'cod_fee' => $q['cod_fee'], 'total' => $q['total'],
                'shipping' => ['courier' => $q['rate']->courier, 'service' => $q['rate']->service, 'estimate' => $q['rate']->estimate, 'source' => 'manual', 'origin' => 'Tasikmalaya', 'weight' => $q['weight']],
                'bank_accounts' => Setting::get('bank_accounts', []), 'coupon_id' => $q['coupon']?->id, 'coupon_code' => $q['coupon']?->code,
                'notes' => $input['notes'] ?? null, 'expires_at' => now()->addHours((int) Setting::get($input['payment_method'] === 'cod' ? 'cod_hours' : 'payment_hours', 24)),
            ]);
            foreach ($q['lines'] as $l) {
                $v = $l['variant'];
                $qty = $l['quantity'];
                $updated = Variant::whereKey($v->id)->whereRaw('stock - reserved >= ?', [$qty])->increment('reserved', $qty);
                $this->check($updated === 1, "Stok {$v->sku} sudah terambil. Perbarui keranjang.");
                $order->items()->create(['variant_id' => $v->id, 'sku' => $v->sku, 'name' => $v->series->name, 'attributes' => ['type' => $v->product_type, 'color' => $v->color, 'size' => $v->size, 'price_group' => $q['price_group']?->name, 'price_tier_minimum' => $l['price_tier_minimum']], 'fulfillment' => $v->fulfillment, 'lead_time' => $v->lead_time, 'weight' => $v->weight, 'price' => $l['unit_price'], 'quantity' => $qty, 'total' => $l['total']]);
                StockMovement::create(['variant_id' => $v->id, 'order_id' => $order->id, 'user_id' => $userId, 'stock_delta' => 0, 'reserved_delta' => $qty, 'reason' => 'Reservasi checkout']);
            }
            $q['coupon']?->increment('used');
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
