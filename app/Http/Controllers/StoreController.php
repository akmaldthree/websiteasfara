<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\CustomQuote;
use App\Models\Order;
use App\Models\Page;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\Series;
use App\Models\ShippingRate;
use App\Models\Variant;
use App\Services\AfterSalesService;
use App\Services\Analytics;
use App\Services\Audit;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CustomProductService;
use App\Services\OrderCommunication;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    public function home(Request $request, Analytics $analytics)
    {
        $analytics->record($request, 'view_home');

        return view('store.home', ['collections' => Series::published()->with('category')->withMin(['variants' => fn ($q) => $q->where('active', true)], 'price')->orderByDesc('featured')->latest()->take(4)->get(), 'contentEntries' => ContentEntry::published()->whereIn('type', ['banner', 'home_section'])->orderBy('sort_order')->get()]);
    }

    public function catalog(Request $r, Analytics $analytics)
    {
        $q = Series::published()->with('category')->withMin(['variants' => fn ($q) => $q->where('active', true)], 'price');
        $currentCategory = null;
        if ($r->filled('q')) {
            $q->where(function ($q) use ($r) {
                $term = '%'.mb_substr($r->string('q'), 0, 100).'%';
                $q->where('name', 'like', $term)->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $term)->orWhere('product_type', 'like', $term));
            });
        }
        if ($r->filled('category')) {
            $currentCategory = Category::where('active', true)->where('slug', $r->input('category'))->firstOrFail();
            $q->where('category_id', $currentCategory->id);
        }
        $q->whereHas('variants', function ($v) use ($r) {
            $v->where('active', true);
            foreach (['color', 'size', 'fulfillment', 'product_type'] as $f) {
                if ($r->filled($f)) {
                    $v->where($f, $r->input($f));
                }
            } if ($r->boolean('available')) {
                $v->whereColumn('stock', '>', 'reserved');
            } if (is_numeric($r->input('min'))) {
                $v->where('price', '>=', max(0, (int) $r->input('min')));
            } if (is_numeric($r->input('max'))) {
                $v->where('price', '<=', max(0, (int) $r->input('max')));
            }
        });
        match ($r->input('sort')) {
            'price_asc' => $q->orderBy('variants_min_price'),'price_desc' => $q->orderByDesc('variants_min_price'),default => $q->latest()
        };

        $collections = $q->paginate(12)->withQueryString();
        if ($r->filled('q')) {
            $analytics->record($r, 'search', ['results' => $collections->total()]);
        } else {
            $analytics->record($r, 'view_category', ['category' => $currentCategory?->slug ?: 'all', 'results' => $collections->total()]);
        }

        return view('store.catalog', compact('collections', 'currentCategory'));
    }

    public function product(Request $r, string $slug, PricingService $pricing, Analytics $analytics)
    {
        $series = Series::published()->where('slug', $slug)->with([
            'category',
            'media' => fn ($q) => $q->where('active', true),
            'variants' => fn ($q) => $q->where('active', true)->with(['measurementTemplate', 'bundleComponents.componentVariant'])->orderBy('id'),
        ])->firstOrFail();
        $priceGroup = $pricing->groupFor($r->user());
        $businessTiers = $series->variants->mapWithKeys(fn (Variant $variant) => [$variant->id => $pricing->tiersFor($variant, $r->user())]);
        $reviews = Review::where('status', 'approved')->whereHas('variant', fn ($query) => $query->where('series_id', $series->id))->with('user')->withCount('helpfulVotes')->latest()->get();
        $questions = ProductQuestion::where('series_id', $series->id)->where('status', 'answered')->with('user')->latest('answered_at')->get();
        $helpfulReviewIds = $r->user() ? ReviewHelpfulVote::where('user_id', $r->user()->id)->whereIn('review_id', $reviews->pluck('id'))->pluck('review_id')->all() : [];
        $wishlistIds = $r->user()?->wishlistItems()->whereIn('variant_id', $series->variants->pluck('id'))->pluck('variant_id')->all() ?? [];
        $analytics->record($r, 'view_item', ['series_id' => $series->id, 'variant_id' => $r->integer('variant') ?: null]);

        return view('store.product', compact('series', 'priceGroup', 'businessTiers', 'reviews', 'questions', 'helpfulReviewIds', 'wishlistIds'));
    }

    public function question(Request $request, Series $series)
    {
        $data = $request->validate(['question' => 'required|string|min:10|max:1000']);
        $question = $series->questions()->create($data + ['user_id' => $request->user()->id]);
        Audit::record('product_question.submitted', 'product_question:'.$question->id, null, ['series_id' => $series->id]);

        return back()->with('success', 'Pertanyaan dikirim dan akan tampil setelah dijawab tim Dthree.');
    }

    public function helpful(Request $request, Review $review)
    {
        abort_unless($review->status === 'approved', 404);
        $vote = $review->helpfulVotes()->where('user_id', $request->user()->id)->first();
        if ($vote) {
            $vote->delete();
            $message = 'Penilaian membantu dibatalkan.';
        } else {
            $review->helpfulVotes()->create(['user_id' => $request->user()->id]);
            $message = 'Terima kasih atas penilaian Anda.';
        }

        return back()->with('success', $message);
    }

    public function cart(Request $r, CheckoutService $service)
    {
        $cart = $r->session()->get('cart', []);
        $lines = $cart ? $service->lines($cart, false, $r->user(), $r->session()->get('cart_customizations', [])) : [];

        return view('store.cart', compact('cart', 'lines'));
    }

    public function add(Request $r, CheckoutService $service, CartService $cartService, Analytics $analytics)
    {
        $d = $r->validate(['variant_id' => 'required|integer|exists:variants,id', 'quantity' => 'required|integer|min:1|max:100']);
        $variant = Variant::findOrFail($d['variant_id']);
        if ($variant->fulfillment === 'custom_made') {
            throw ValidationException::withMessages(['variant_id' => 'Gunakan formulir ukuran custom pada halaman produk.']);
        }
        $cart = $r->session()->get('cart', []);
        $cart[$d['variant_id']] = ($cart[$d['variant_id']] ?? 0) + $d['quantity'];
        $service->lines($cart, false, $r->user(), $r->session()->get('cart_customizations', []));
        $r->session()->put('cart', $cart);
        if ($r->user()) {
            $cartService->sync($r->user(), $cart, $r->session()->get('cart_customizations', []));
        }
        $r->session()->forget('checkout_key');
        $analytics->record($r, 'add_to_cart', ['variant_id' => $variant->id, 'sku' => $variant->sku, 'quantity' => $d['quantity'], 'value' => $variant->price * $d['quantity']]);

        return redirect()->route('cart')->with('success', 'Pilihan Anda sudah ditambahkan.');
    }

    public function updateCart(Request $r, CartService $cartService)
    {
        $d = $r->validate(['variant_id' => 'required|integer', 'quantity' => 'required|integer|min:0|max:100']);
        $cart = $r->session()->get('cart', []);
        if ($d['quantity'] === 0) {
            unset($cart[$d['variant_id']]);
            $customizations = $r->session()->get('cart_customizations', []);
            unset($customizations[$d['variant_id']]);
            $r->session()->put('cart_customizations', $customizations);
        } elseif (isset($cart[$d['variant_id']])) {
            $cart[$d['variant_id']] = $d['quantity'];
        }
        $r->session()->put('cart', $cart);
        if ($r->user()) {
            $cartService->sync($r->user(), $cart, $r->session()->get('cart_customizations', []));
        }
        $r->session()->forget('checkout_key');

        return back();
    }

    public function checkout(Request $r, CheckoutService $service, Analytics $analytics)
    {
        if (! $r->session()->get('cart')) {
            return redirect()->route('cart');
        }
        if (! $r->session()->has('checkout_key')) {
            $r->session()->put('checkout_key', Str::random(64));
        }
        $lines = $service->lines($r->session()->get('cart'), false, $r->user(), $r->session()->get('cart_customizations', []));
        $rates = ShippingRate::where('active', true)->get();

        $businessAccount = $r->user()?->businessAccount;
        $analytics->record($r, 'begin_checkout', ['items' => array_sum(array_column($lines, 'quantity')), 'value' => array_sum(array_column($lines, 'total'))]);

        return view('store.checkout', compact('lines', 'rates', 'businessAccount'));
    }

    private function checkoutData(Request $r): array
    {
        return $r->validate([
            'name' => 'required|string|max:100', 'phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/'], 'email' => 'nullable|email|max:150',
            'province' => 'required|string|max:100', 'city' => 'required|string|max:100', 'district' => 'required|string|max:100',
            'postal_code' => 'required|digits:5', 'detail' => 'required|string|max:1000', 'shipping_rate_id' => 'required|integer',
            'payment_method' => 'required|in:transfer,cod', 'coupon' => 'nullable|string|max:200', 'loyalty_points' => 'nullable|integer|min:0|max:100000000', 'notes' => 'nullable|string|max:1000', 'terms' => 'accepted',
            'sender_name' => 'nullable|string|max:100', 'sender_phone' => ['nullable', 'regex:/^\+?[0-9]{9,15}$/'],
        ]);
    }

    public function review(Request $r, CheckoutService $service, Analytics $analytics)
    {
        $data = $this->checkoutData($r);
        $quote = $service->quote($r->session()->get('cart', []), $data, false, $r->user(), $r->session()->get('cart_customizations', []));
        $r->session()->put('checkout_data', $data);
        $r->session()->put('checkout_quote', $service->fingerprint($quote));
        $analytics->record($r, 'shipping_selected', ['courier' => $quote['rate']->courier, 'service' => $quote['rate']->service, 'value' => $quote['shipping_cost']]);
        $analytics->record($r, 'payment_selected', ['method' => $data['payment_method'], 'value' => $quote['total']]);

        return view('store.review', compact('data', 'quote'));
    }

    public function place(Request $r, CheckoutService $service, CartService $cartService, Analytics $analytics)
    {
        $r->validate(['checkout_key' => 'required|string|size:64']);
        abort_unless(hash_equals($r->session()->get('checkout_key', ''), $r->input('checkout_key')), 419);
        if ($existing = Order::where('checkout_key', $r->input('checkout_key'))->first()) {
            return redirect($existing->customerUrl());
        }
        $data = $r->session()->get('checkout_data');
        abort_unless($data, 419);
        abort_unless($r->session()->has('checkout_quote'), 419);
        $o = $service->place($r->session()->get('cart', []), $data, $r->input('checkout_key'), $r->user()?->id, $r->session()->get('checkout_quote'), $r->session()->get('cart_customizations', []));
        $r->session()->forget(['cart', 'cart_customizations', 'checkout_data', 'checkout_quote']);
        if ($r->user()) {
            $cartService->sync($r->user(), []);
        }
        $analytics->record($r, 'purchase', ['value' => $o->total, 'items' => $o->items()->sum('quantity'), 'method' => $o->payment_method]);

        return redirect($o->customerUrl())->with('success', 'Pesanan berhasil dibuat. Simpan tautan halaman ini.');
    }

    public function customize(Request $r, Variant $variant, CustomProductService $service, CartService $cartService)
    {
        $data = $r->validate(['request_key' => 'required|uuid', 'measurements' => 'required|array', 'measurement_approval' => 'accepted']);
        if ($variant->measurementTemplate?->pricing_mode === 'quote') {
            if (! $r->user()) {
                return redirect()->route('login')->withErrors(['custom' => 'Masuk atau daftar untuk meminta penawaran custom.']);
            }
            $quote = $service->requestQuote($variant, $r->user(), $data['measurements'], $data['request_key']);

            return redirect()->route('account')->with('success', 'Permintaan penawaran '.$quote->number.' dikirim.');
        }
        $customization = $service->fixedCustomization($variant, $data['measurements']);
        $cart = $r->session()->get('cart', []);
        $customizations = $r->session()->get('cart_customizations', []);
        $cart[$variant->id] = 1;
        $customizations[$variant->id] = $customization;
        $r->session()->put(['cart' => $cart, 'cart_customizations' => $customizations]);
        if ($r->user()) {
            $cartService->sync($r->user(), $cart, $customizations);
        }
        $r->session()->forget('checkout_key');

        return redirect()->route('cart')->with('success', 'Ukuran custom disetujui dan ditambahkan ke keranjang.');
    }

    public function acceptCustomQuote(Request $r, CustomQuote $customQuote, CustomProductService $service, CartService $cartService)
    {
        $customization = $service->accept($customQuote, $r->user());
        $cart = $r->session()->get('cart', []);
        $customizations = $r->session()->get('cart_customizations', []);
        $cart[$customQuote->variant_id] = 1;
        $customizations[$customQuote->variant_id] = $customization;
        $r->session()->put(['cart' => $cart, 'cart_customizations' => $customizations]);
        $cartService->sync($r->user(), $cart, $customizations);
        $r->session()->forget('checkout_key');

        return redirect()->route('cart')->with('success', 'Penawaran diterima dan produk custom ditambahkan ke keranjang.');
    }

    public function order(Order $order)
    {
        $order->load('items.components', 'proofs', 'cases.items.item', 'refunds', 'events');

        return view('store.order', compact('order'));
    }

    public function proof(Request $r, Order $order)
    {
        $r->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = $r->file('proof')->store('payment-proofs', 'local');
        try {
            DB::transaction(function () use ($order, $path) {
                $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($o->payment_method !== 'transfer' || $o->status !== 'waiting' || ! in_array($o->payment_status, ['unpaid', 'rejected']) || $o->expires_at->isPast()) {
                    throw ValidationException::withMessages(['proof' => 'Pesanan tidak menerima bukti baru atau sudah kedaluwarsa.']);
                }
                $proof = $o->proofs()->create(['path' => $path]);
                $o->update(['payment_status' => 'review']);
                Audit::record('payment.uploaded', 'order:'.$o->id);
                OrderCommunication::record($o, 'proof-'.$proof->id, 'proof_uploaded', 'Bukti transfer diterima dan menunggu verifikasi tim Keuangan.');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return redirect($order->customerUrl())->with('success', 'Bukti diterima. Tim Keuangan akan memverifikasi pembayaran.');
    }

    public function track(Request $r)
    {
        $d = $r->validate(['number' => 'required|string|max:50', 'phone' => 'required|string|max:30']);
        $o = Order::where('number', strtoupper(trim($d['number'])))->where('phone', trim($d['phone']))->first();
        if (! $o) {
            throw ValidationException::withMessages(['number' => 'Nomor pesanan atau nomor telepon tidak cocok.']);
        }

        return redirect($o->customerUrl());
    }

    public function serviceCase(Request $r, Order $order, AfterSalesService $service)
    {
        $d = $r->validate([
            'request_key' => 'required|uuid', 'type' => 'required|in:return,warranty,complaint',
            'reason' => 'required|string|min:10|max:2000', 'reason_code' => 'nullable|string|max:100',
            'items' => 'nullable|array|max:50', 'items.*' => 'nullable|integer|min:0|max:100',
            'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        $path = $r->file('evidence')?->store('case-evidence', 'local');
        try {
            $case = $service->open($order, $d, $path);
            if ($path && $case->evidence_path !== $path) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect($order->customerUrl())->with('success', 'Kasus '.$case->number.' tercatat. Tim kami akan meninjau permintaan Anda.');
    }

    public function page(string $slug)
    {
        return view('store.page', ['page' => Page::published()->where('slug', $slug)->firstOrFail()]);
    }

    public function content(string $type, string $slug)
    {
        abort_unless(in_array($type, ['faq', 'journal', 'campaign']), 404);

        return view('store.content', ['entry' => ContentEntry::published()->where('type', $type)->where('slug', $slug)->firstOrFail()]);
    }
}
