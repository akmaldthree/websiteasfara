<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\CustomQuote;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Page;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\Series;
use App\Models\Setting;
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
        $contentEntries = ContentEntry::published()->whereIn('type', ['banner', 'home_section'])->orderBy('sort_order')->get();
        $collections = Series::published()
            ->with(['category', 'media' => fn ($query) => $query->where('active', true), 'variants' => fn ($query) => $query->where('active', true)])
            ->withMin(['variants' => fn ($query) => $query->where('active', true)], 'price')
            ->withSum(['orderItems as sold_quantity' => fn ($query) => $query->whereHas('order', fn ($order) => $order->whereNotIn('status', ['cancelled']))], 'quantity')
            ->withCount('reviews')->withAvg('reviews', 'rating')
            ->orderByDesc('featured')->latest()->take(24)->get();
        $lookProductIds = $contentEntries->flatMap(fn ($entry) => collect($entry->blocks)->where('type', 'product_hotspot')->pluck('series_id'))->filter()->unique();
        $lookProducts = Series::published()->whereIn('id', $lookProductIds)
            ->with(['media' => fn ($query) => $query->where('active', true), 'variants' => fn ($query) => $query->where('active', true)])
            ->withMin(['variants' => fn ($query) => $query->where('active', true)], 'price')->get()->keyBy('id');

        return view('store.home', compact('collections', 'contentEntries', 'lookProducts'));
    }

    public function catalog(Request $r, Analytics $analytics)
    {
        $q = Series::published()->with([
            'category',
            'media' => fn ($query) => $query->where('active', true),
            'variants' => fn ($query) => $query->where('active', true),
        ])->withMin(['variants' => fn ($q) => $q->where('active', true)], 'price')
            ->withSum(['orderItems as sold_quantity' => fn ($query) => $query->whereHas('order', fn ($order) => $order->whereNotIn('status', ['cancelled']))], 'quantity')
            ->withCount('reviews')->withAvg('reviews', 'rating');
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
        if ($r->boolean('sale')) {
            $q->where('compare_at_price', '>', 0);
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
            'featured' => $q->orderByDesc('featured')->latest(),
            'best_selling' => $q->orderByDesc('sold_quantity')->latest(),
            'price_asc' => $q->orderBy('variants_min_price'),
            'price_desc' => $q->orderByDesc('variants_min_price'),
            'title_asc' => $q->orderBy('name'),
            'title_desc' => $q->orderByDesc('name'),
            'oldest' => $q->oldest(),
            default => $q->latest(),
        };

        $collections = $q->paginate(12)->withQueryString();
        if ($r->filled('q')) {
            $analytics->record($r, 'search', ['results' => $collections->total()]);
        } else {
            $analytics->record($r, 'view_category', ['category' => $currentCategory?->slug ?: 'all', 'results' => $collections->total()]);
        }

        $recentlyViewed = $this->recentlyViewed($r);

        $filterOptions = [
            'colors' => Variant::where('active', true)->whereHas('series', fn ($series) => $series->published())->select('color')->selectRaw('count(distinct series_id) as total')->whereNotNull('color')->groupBy('color')->orderBy('color')->get(),
            'sizes' => Variant::where('active', true)->whereHas('series', fn ($series) => $series->published())->select('size')->selectRaw('count(distinct series_id) as total')->whereNotNull('size')->groupBy('size')->orderBy('size')->get(),
            'types' => Variant::where('active', true)->whereHas('series', fn ($series) => $series->published())->select('product_type')->selectRaw('count(distinct series_id) as total')->whereNotNull('product_type')->where('product_type', '!=', '')->groupBy('product_type')->orderBy('product_type')->get(),
        ];

        return view('store.catalog', compact('collections', 'currentCategory', 'recentlyViewed', 'filterOptions'));
    }

    public function searchSuggestions(Request $request)
    {
        $term = trim(mb_substr((string) $request->query('q'), 0, 100));
        if (mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Series::published()
            ->where(function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhereHas('variants', fn ($variant) => $variant->where('active', true)->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('color', 'like', $like)->orWhere('size', 'like', $like)));
            })
            ->with(['media' => fn ($query) => $query->where('active', true), 'variants' => fn ($query) => $query->where('active', true)])
            ->take(6)->get()->map(fn (Series $series) => [
                'name' => $series->name,
                'url' => route('product', $series->slug),
                'image' => $series->media->first()?->image_url ?? $series->image_url,
                'price' => 'Rp '.number_format((int) $series->variants->min('price'), 0, ',', '.'),
            ]);

        return response()->json(['products' => $products]);
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
        $isWishlisted = $r->user()?->wishlistItems()->where('series_id', $series->id)->exists() ?? false;
        $relatedProducts = Series::published()
            ->whereKeyNot($series->id)
            ->where('category_id', $series->category_id)
            ->with(['category', 'media' => fn ($query) => $query->where('active', true), 'variants' => fn ($query) => $query->where('active', true)])
            ->withMin(['variants as variants_min_price' => fn ($query) => $query->where('active', true)], 'price')
            ->withCount(['reviews' => fn ($query) => $query->where('status', 'approved')])
            ->withAvg(['reviews' => fn ($query) => $query->where('status', 'approved')], 'rating')
            ->orderByDesc('featured')->latest('id')->take(8)->get();
        $recentlyViewedIds = collect($r->session()->get('recently_viewed', []))->prepend($series->id)->unique()->take(8)->values()->all();
        $r->session()->put('recently_viewed', $recentlyViewedIds);
        $analytics->record($r, 'view_item', ['series_id' => $series->id, 'variant_id' => $r->integer('variant') ?: null]);

        return view('store.product', compact('series', 'priceGroup', 'businessTiers', 'reviews', 'questions', 'helpfulReviewIds', 'isWishlisted', 'relatedProducts'));
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

    public function cart(Request $r, CheckoutService $service, CartService $cartService)
    {
        $cart = $this->normalizedCart($r, $cartService);
        $lines = $cart ? $service->lines($cart, false, $r->user(), $r->session()->get('cart_customizations', [])) : [];
        $recentlyViewed = $this->recentlyViewed($r);
        $recommendations = $this->cartRecommendations($lines);

        return view('store.cart', compact('cart', 'lines', 'recentlyViewed', 'recommendations'));
    }

    public function cartSummary(Request $request, CheckoutService $service, CartService $cartService)
    {
        $cart = $this->normalizedCart($request, $cartService);
        $lines = $cart ? $service->lines($cart, false, $request->user(), $request->session()->get('cart_customizations', [])) : [];
        $subtotal = array_sum(array_column($lines, 'total'));
        $target = max(0, (int) Setting::get('free_shipping_threshold', 500000));
        $recommendations = $this->cartRecommendations($lines)->take(3)->map(fn ($series) => [
            'name' => $series->name,
            'price' => 'Rp '.number_format((int) $series->variants_min_price, 0, ',', '.'),
            'image' => $series->image_url,
            'url' => route('product', $series->slug),
        ])->values();

        return response()->json([
            'count' => array_sum($cart),
            'subtotal' => 'Rp '.number_format($subtotal, 0, ',', '.'),
            'free_shipping_target' => $target,
            'free_shipping_remaining' => max(0, $target - $subtotal),
            'free_shipping_progress' => $target > 0 ? min(100, (int) round($subtotal / $target * 100)) : 100,
            'lines' => collect($lines)->map(fn ($line) => [
                'variant_id' => $line['variant']->id,
                'name' => $line['variant']->series->name,
                'variant' => $line['variant']->label,
                'quantity' => $line['quantity'],
                'total' => 'Rp '.number_format($line['total'], 0, ',', '.'),
                'image' => $line['variant']->series->image_url,
                'url' => route('product', $line['variant']->series->slug),
            ])->values(),
            'recommendations' => $recommendations,
        ]);
    }

    public function add(Request $r, CheckoutService $service, CartService $cartService, Analytics $analytics)
    {
        $d = $r->validate(['variant_id' => 'required|integer|exists:variants,id', 'quantity' => 'required|integer|min:1|max:100', 'buy_now' => 'nullable|boolean']);
        $variant = Variant::findOrFail($d['variant_id']);
        if ($variant->fulfillment === 'custom_made') {
            throw ValidationException::withMessages(['variant_id' => 'Gunakan formulir ukuran custom pada halaman produk.']);
        }
        $cart = $this->normalizedCart($r, $cartService);
        $currentQuantity = (int) ($cart[$d['variant_id']] ?? 0);
        $cart[$d['variant_id']] = $r->boolean('buy_now')
            ? $d['quantity']
            : min(100, $currentQuantity + $d['quantity']);
        $service->lines($cart, false, $r->user(), $r->session()->get('cart_customizations', []));
        $r->session()->put('cart', $cart);
        if ($r->user()) {
            $cartService->sync($r->user(), $cart, $r->session()->get('cart_customizations', []));
        }
        $r->session()->forget('checkout_key');
        $analytics->record($r, 'add_to_cart', ['variant_id' => $variant->id, 'sku' => $variant->sku, 'quantity' => $d['quantity'], 'value' => $variant->price * $d['quantity']]);

        return redirect()->route($r->boolean('buy_now') ? 'checkout' : 'cart')->with('success', 'Pilihan Anda sudah ditambahkan.');
    }

    private function recentlyViewed(Request $request)
    {
        $ids = collect($request->session()->get('recently_viewed', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Series::published()->whereIn('id', $ids)->with([
            'category',
            'media' => fn ($query) => $query->where('active', true),
            'variants' => fn ($query) => $query->where('active', true),
        ])->withMin(['variants' => fn ($query) => $query->where('active', true)], 'price')->withCount('reviews')->withAvg('reviews', 'rating')->get()->sortBy(fn ($series) => $ids->search($series->id))->values();
    }

    private function cartRecommendations(array $lines)
    {
        $seriesIds = collect($lines)->pluck('variant.series_id')->filter()->unique();
        $categoryIds = collect($lines)->pluck('variant.series.category_id')->filter()->unique();

        return Series::published()->when($seriesIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $seriesIds))
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->with([
                'category',
                'media' => fn ($query) => $query->where('active', true),
                'variants' => fn ($query) => $query->where('active', true),
            ])->withMin(['variants' => fn ($query) => $query->where('active', true)], 'price')
            ->withCount('reviews')->withAvg('reviews', 'rating')->orderByDesc('featured')->take(8)->get();
    }

    public function updateCart(Request $r, CartService $cartService, CheckoutService $checkoutService)
    {
        $d = $r->validate(['variant_id' => 'required|integer', 'quantity' => 'required|integer|min:0|max:100']);
        $cart = $this->normalizedCart($r, $cartService);
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

        if ($r->expectsJson()) {
            return $this->cartSummary($r, $checkoutService, $cartService);
        }

        return back();
    }

    public function checkout(Request $r, CheckoutService $service, CartService $cartService, Analytics $analytics)
    {
        $cart = $this->normalizedCart($r, $cartService);
        if (! $cart) {
            return redirect()->route('cart');
        }
        if (! $r->session()->has('checkout_key')) {
            $r->session()->put('checkout_key', Str::random(64));
        }
        $lines = $service->lines($cart, false, $r->user(), $r->session()->get('cart_customizations', []));
        $weight = array_sum(array_map(fn ($line) => $line['unit_weight'] * $line['quantity'], $lines));
        $billableKilograms = max(1, (int) ceil($weight / 1000));
        $rates = ShippingRate::where('active', true)->get()
            ->each(fn (ShippingRate $rate) => $rate->setAttribute('checkout_cost', $rate->first_kg + ($billableKilograms - 1) * $rate->extra_kg))
            ->sortBy(fn (ShippingRate $rate) => [$rate->checkout_cost, $rate->id])
            ->unique(fn (ShippingRate $rate) => Str::lower(trim($rate->province).'|'.trim($rate->city)))
            ->values();

        $businessAccount = $r->user()?->businessAccount;
        $analytics->record($r, 'begin_checkout', ['items' => array_sum(array_column($lines, 'quantity')), 'value' => array_sum(array_column($lines, 'total'))]);

        return view('store.checkout', compact('lines', 'rates', 'businessAccount'));
    }

    private function normalizedCart(Request $request, CartService $cartService): array
    {
        $original = $request->session()->get('cart', []);
        $cart = $cartService->normalize(is_array($original) ? $original : []);
        if ($cart !== $original) {
            $storedCustomizations = $request->session()->get('cart_customizations', []);
            $customizations = array_intersect_key(is_array($storedCustomizations) ? $storedCustomizations : [], $cart);
            $request->session()->put(['cart' => $cart, 'cart_customizations' => $customizations]);
            if ($request->user()) {
                $cartService->sync($request->user(), $cart, $customizations);
            }
        }

        return $cart;
    }

    private function checkoutData(Request $r): array
    {
        return $r->validate([
            'name' => 'required|string|max:100', 'phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/'], 'email' => 'nullable|email|max:150',
            'province' => 'required|string|max:100', 'city' => 'required|string|max:100', 'district' => 'required|string|max:100',
            'postal_code' => 'required|digits:5', 'detail' => 'required|string|max:1000', 'shipping_rate_id' => 'required|integer',
            'payment_method' => 'required|in:transfer,cod,credit', 'coupon' => 'nullable|string|max:200', 'loyalty_points' => 'nullable|integer|min:0|max:100000000', 'notes' => 'nullable|string|max:1000', 'terms' => 'accepted',
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
        $data = $r->validate(['request_key' => 'required|uuid', 'measurements' => 'required|array', 'measurement_approval' => 'accepted', 'reference' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        if ($variant->measurementTemplate?->pricing_mode === 'quote') {
            if (! $r->user()) {
                return redirect()->route('login')->withErrors(['custom' => 'Masuk atau daftar untuk meminta penawaran custom.']);
            }
        }
        $referencePath = $r->hasFile('reference') ? $r->file('reference')->store('custom-references', 'local') : null;
        try {
            if ($variant->measurementTemplate?->pricing_mode === 'quote') {
                $quote = $service->requestQuote($variant, $r->user(), $data['measurements'], $data['request_key'], $referencePath);
                if ($referencePath && $quote->reference_path !== $referencePath) {
                    Storage::disk('local')->delete($referencePath);
                }

                return redirect()->route('account')->with('success', 'Permintaan penawaran '.$quote->number.' dikirim.');
            }
            $customization = $service->fixedCustomization($variant, $data['measurements'], $referencePath);
        } catch (\Throwable $exception) {
            if ($referencePath) {
                Storage::disk('local')->delete($referencePath);
            }
            throw $exception;
        }
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
        abort_unless(in_array($type, ['faq', 'journal', 'lookbook', 'campaign']), 404);

        return view('store.content', ['entry' => ContentEntry::published()->where('type', $type)->where('slug', $slug)->firstOrFail()]);
    }

    public function contentIndex(string $type)
    {
        abort_unless(in_array($type, ['journal', 'lookbook', 'campaign']), 404);
        $entries = ContentEntry::published()->where('type', $type)->orderBy('sort_order')->latest()->paginate(12);

        return view('store.content-index', compact('type', 'entries'));
    }

    public function newsletter(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:150',
            'name' => 'nullable|string|max:100',
            'consent' => 'accepted',
        ]);
        NewsletterSubscriber::updateOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            ['name' => $data['name'] ?? null, 'source' => 'footer', 'active' => true, 'subscribed_at' => now()]
        );

        return back()->with('success', 'Terima kasih. Anda sudah terdaftar untuk menerima kabar Dthree.');
    }
}
