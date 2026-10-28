<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\AuditLog;
use App\Models\BusinessAccount;
use App\Models\BusinessOrderRule;
use App\Models\BundleComponent;
use App\Models\CaseEvent;
use App\Models\CaseItem;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\CuratedLink;
use App\Models\CustomQuote;
use App\Models\MediaAsset;
use App\Models\MeasurementTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\PaymentProof;
use App\Models\PriceGroup;
use App\Models\ProductMedia;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\Series;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\StockMovement;
use App\Models\StoreLocation;
use App\Models\User;
use App\Models\Variant;
use App\Models\VariantPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoSeedingEnabled = config('app.demo_seeding_enabled') || filter_var(env('ALLOW_DEMO_SEEDING', false), FILTER_VALIDATE_BOOL);
        if (! app()->environment(['local', 'testing']) && ! $demoSeedingEnabled) {
            throw new \RuntimeException('Aktifkan ALLOW_DEMO_SEEDING=true untuk memasang data demonstrasi di luar local/testing.');
        }

        $this->call(DatabaseSeeder::class);
        $this->publishImages();

        $categories = collect([
            ['name' => 'Abaya Dewasa', 'slug' => 'abaya-dewasa', 'description' => 'Abaya modern dengan siluet anggun untuk kegiatan harian dan acara istimewa.'],
            ['name' => 'Sarimbit Keluarga', 'slug' => 'sarimbit-keluarga', 'description' => 'Busana keluarga senada untuk momen hangat dan perayaan bersama.'],
            ['name' => 'Busana Anak', 'slug' => 'busana-anak', 'description' => 'Busana santun anak yang ringan, rapi, dan nyaman bergerak.'],
            ['name' => 'Outer & Khimar', 'slug' => 'outer-khimar', 'description' => 'Pelengkap tampilan modest yang mudah dipadukan dengan koleksi Dthree.'],
        ])->mapWithKeys(function (array $data) {
            $category = Category::updateOrCreate(['slug' => $data['slug']], $data + [
                'seo_title' => $data['name'].' Dthree',
                'seo_description' => $data['description'],
                'active' => true,
            ]);

            return [$data['slug'] => $category];
        });

        foreach ($this->products() as $index => $product) {
            $series = Series::updateOrCreate(['slug' => $product['slug']], [
                'name' => $product['name'],
                'category_id' => $categories[$product['category']]->id,
                'description' => $product['description'],
                'material' => $product['material'],
                'care' => 'Cuci lembut dengan warna serupa, hindari pemutih, dan setrika dengan suhu rendah.',
                'size_guide' => "S: LD 96 cm · panjang 136 cm\nM: LD 102 cm · panjang 138 cm\nL: LD 108 cm · panjang 140 cm\nXL: LD 114 cm · panjang 142 cm",
                'size_chart' => ['columns' => ['Ukuran', 'Lingkar dada', 'Panjang'], 'rows' => collect($product['sizes'])->map(fn ($size, $position) => [$size, (96 + $position * 6).' cm', (136 + $position * 2).' cm'])->all(), 'notes' => 'Ukuran dalam sentimeter. Pilih ukuran lebih besar jika berada di antara dua ukuran.'],
                'image' => 'demo/'.$product['image'],
                'compare_at_price' => (int) (ceil(($product['price'] * 1.2) / 1000) * 1000),
                'seo_title' => $product['name'].' — Koleksi Dthree',
                'seo_description' => $product['description'],
                'active' => true,
                'featured' => $index < 6,
                'uses_product_type' => $product['types'] !== [''],
            ]);

            $this->seedVariants($series, $product);
            $this->seedMedia($series, $product);
        }

        $this->seedReviews();
        $this->seedCatalogWorkflows($categories);
        $this->seedOperationalExamples();
        $this->seedAudienceAndMedia();
        $this->seedShippingRates();
        $this->seedStoreLocations();
        $this->seedCuratedLinks();
        $this->seedContent();
        $this->seedCoupons();
        $this->seedSettings();
        $this->seedPages();
    }

    private function seedCatalogWorkflows($categories): void
    {
        $gamisTemplate = MeasurementTemplate::updateOrCreate(['name' => 'Ukuran Custom Gamis & Abaya'], [
            'product_type' => null,
            'pricing_mode' => 'fixed',
            'surcharge' => 75000,
            'lead_time' => '10–14 hari kerja',
            'fields' => [
                ['key' => 'chest', 'label' => 'Lingkar dada', 'unit' => 'cm', 'min' => 70, 'max' => 160, 'required' => true, 'help' => 'Ukur bagian dada terlebar dengan meteran tidak terlalu ketat.', 'example' => 100],
                ['key' => 'waist', 'label' => 'Lingkar pinggang', 'unit' => 'cm', 'min' => 60, 'max' => 160, 'required' => true, 'help' => 'Ukur mengelilingi pinggang pada posisi alami.', 'example' => 88],
                ['key' => 'length', 'label' => 'Panjang baju', 'unit' => 'cm', 'min' => 110, 'max' => 165, 'required' => true, 'help' => 'Ukur dari titik bahu tertinggi hingga panjang yang diinginkan.', 'example' => 140],
                ['key' => 'sleeve', 'label' => 'Panjang lengan', 'unit' => 'cm', 'min' => 45, 'max' => 75, 'required' => true, 'help' => 'Ukur dari pangkal bahu sampai pergelangan.', 'example' => 58],
            ],
            'policy' => 'Ukuran diproduksi berdasarkan data pelanggan. Perubahan ukuran dapat dikonfirmasi maksimal 24 jam setelah penawaran diterima.',
            'active' => true,
        ]);

        MeasurementTemplate::updateOrCreate(['name' => 'Ukuran Custom Koko'], [
            'product_type' => 'Koko Ayah', 'pricing_mode' => 'quote', 'surcharge' => 0, 'lead_time' => '10–14 hari kerja',
            'fields' => [
                ['key' => 'chest', 'label' => 'Lingkar dada', 'unit' => 'cm', 'min' => 75, 'max' => 170, 'required' => true, 'help' => 'Ukur bagian dada terlebar.', 'example' => 104],
                ['key' => 'shirt_length', 'label' => 'Panjang baju', 'unit' => 'cm', 'min' => 60, 'max' => 100, 'required' => true, 'help' => 'Ukur dari bahu sampai panjang yang diinginkan.', 'example' => 78],
                ['key' => 'sleeve', 'label' => 'Panjang lengan', 'unit' => 'cm', 'min' => 45, 'max' => 80, 'required' => true, 'help' => 'Ukur dari bahu hingga pergelangan.', 'example' => 61],
            ],
            'policy' => 'Harga final mengikuti hasil pemeriksaan ukuran dan detail model oleh admin.', 'active' => true,
        ]);

        $alesha = Series::where('slug', 'alesha-everyday')->firstOrFail();
        $customVariant = Variant::updateOrCreate(['sku' => 'DEMO-ALE-STD-IVO-CUSTOM'], [
            'series_id' => $alesha->id, 'product_type' => '', 'color' => 'Ivory', 'size' => 'Custom', 'price' => 399000,
            'stock' => 0, 'reserved' => 0, 'low_stock' => 0, 'weight' => 700, 'fulfillment' => 'custom_made',
            'measurement_template_id' => $gamisTemplate->id, 'lead_time' => '10–14 hari kerja', 'active' => true,
        ]);

        $arcelia = Series::where('slug', 'arcelia-family')->firstOrFail();
        $bundleVariant = Variant::updateOrCreate(['sku' => 'DEMO-ARC-GIB-BUN-FAMILY'], [
            'series_id' => $arcelia->id, 'product_type' => 'Gamis Ibu', 'color' => 'Sage Family Set', 'size' => 'Family Set', 'price' => 899000,
            'stock' => 0, 'reserved' => 0, 'low_stock' => 0, 'weight' => 2100, 'fulfillment' => 'bundle',
            'measurement_template_id' => null, 'lead_time' => null, 'active' => true,
        ]);
        foreach (['DEMO-ARC-GIB-SAG-S', 'DEMO-ARC-KAY-SAG-S', 'DEMO-ARC-KAN-SAG-S', 'DEMO-ARC-GAN-SAG-S'] as $componentSku) {
            if ($component = Variant::where('sku', $componentSku)->first()) {
                BundleComponent::updateOrCreate(
                    ['bundle_variant_id' => $bundleVariant->id, 'component_variant_id' => $component->id],
                    ['quantity' => 1]
                );
            }
        }

        $groups = collect([
            ['slug' => 'reseller', 'name' => 'Reseller', 'business_type' => 'reseller', 'minimum_order_quantity' => 3, 'minimum_order_amount' => 750000, 'discount_percent' => 10],
            ['slug' => 'dropshipper', 'name' => 'Dropshipper', 'business_type' => 'dropshipper', 'minimum_order_quantity' => 1, 'minimum_order_amount' => 0, 'discount_percent' => 5],
            ['slug' => 'grosir', 'name' => 'Grosir', 'business_type' => 'wholesale', 'minimum_order_quantity' => 12, 'minimum_order_amount' => 2500000, 'discount_percent' => 18],
        ])->mapWithKeys(function ($data) {
            $group = PriceGroup::updateOrCreate(['slug' => $data['slug']], $data + ['active' => true]);
            return [$data['slug'] => $group];
        });

        Variant::where('sku', 'like', 'DEMO-%')->where('fulfillment', 'ready_stock')->orderBy('id')->take(24)->get()->each(function (Variant $variant) use ($groups) {
            foreach (['reseller' => 0.90, 'dropshipper' => 0.95, 'grosir' => 0.82] as $slug => $factor) {
                $minimum = $groups[$slug]->minimum_order_quantity;
                VariantPrice::updateOrCreate(
                    ['variant_id' => $variant->id, 'price_group_id' => $groups[$slug]->id, 'minimum_quantity' => $minimum],
                    ['price' => (int) (floor(($variant->price * $factor) / 1000) * 1000), 'active' => true]
                );
            }
        });

        foreach (['reseller' => [3, 750000], 'dropshipper' => [1, 0], 'grosir' => [12, 2500000]] as $slug => [$minimumQuantity, $minimumAmount]) {
            BusinessOrderRule::updateOrCreate(
                ['price_group_id' => $groups[$slug]->id, 'target_type' => 'category', 'target_id' => $categories['abaya-dewasa']->id],
                ['minimum_quantity' => $minimumQuantity, 'minimum_amount' => $minimumAmount, 'active' => true]
            );
        }

        $businessUser = User::updateOrCreate(['email' => 'reseller.demo@dthree.co.id'], [
            'name' => 'Reseller Demo Dthree', 'phone' => '6281211112222', 'password' => Str::random(64),
            'marketing_email' => true, 'marketing_whatsapp' => true, 'consent_updated_at' => now(),
        ]);
        BusinessAccount::updateOrCreate(['user_id' => $businessUser->id], [
            'business_type' => 'reseller', 'business_name' => 'Butik Sahabat Demo', 'sales_channel' => 'Instagram dan marketplace',
            'notes' => 'Akun contoh untuk melihat alur pelanggan bisnis.', 'status' => 'approved', 'price_group_id' => $groups['reseller']->id,
            'submitted_at' => now()->subMonths(2), 'reviewed_at' => now()->subMonths(2)->addDay(), 'expires_at' => now()->addYear(),
        ]);
        CustomQuote::updateOrCreate(['request_key' => '00000000-0000-4000-8000-000000000001'], [
            'number' => 'DTH-CUSTOM-DEMO-001', 'user_id' => $businessUser->id, 'variant_id' => $customVariant->id,
            'measurement_template_id' => $gamisTemplate->id, 'template_snapshot' => $gamisTemplate->toArray(),
            'measurements' => ['chest' => 104, 'waist' => 96, 'length' => 143, 'sleeve' => 59], 'status' => 'quoted',
            'surcharge' => 75000, 'lead_time' => '10–14 hari kerja', 'admin_notes' => 'Contoh penawaran ukuran custom yang siap ditinjau.',
            'quoted_at' => now()->subDays(2), 'expires_at' => now()->addDays(12),
        ]);
    }

    private function seedOperationalExamples(): void
    {
        $customers = collect([
            ['Alya Ramadhani', 'alya.demo@dthree.co.id', '6281210010001'],
            ['Nadia Pratiwi', 'nadia.demo@dthree.co.id', '6281210010002'],
            ['Siti Rahma', 'siti.demo@dthree.co.id', '6281210010003'],
            ['Maya Kurnia', 'maya.demo@dthree.co.id', '6281210010004'],
            ['Dina Azzahra', 'dina.demo@dthree.co.id', '6281210010005'],
            ['Rani Permata', 'rani.demo@dthree.co.id', '6281210010006'],
            ['Kania Putri', 'kania.demo@dthree.co.id', '6281210010007'],
        ])->map(fn ($row) => User::updateOrCreate(['email' => $row[1]], [
            'name' => $row[0], 'phone' => $row[2], 'password' => Str::random(64),
            'marketing_email' => true, 'marketing_whatsapp' => true, 'consent_updated_at' => now()->subDays(30),
        ]))->values();
        $variants = Variant::where('sku', 'like', 'DEMO-%')->where('fulfillment', 'ready_stock')->with('series')->orderBy('id')->take(7)->get();
        $states = [
            ['waiting', 'unpaid', 'unprocessed', 'reserved'],
            ['waiting', 'review', 'unprocessed', 'reserved'],
            ['confirmed', 'paid', 'processing', 'committed'],
            ['confirmed', 'paid', 'packed', 'committed'],
            ['confirmed', 'paid', 'shipped', 'committed'],
            ['completed', 'paid', 'received', 'committed'],
            ['cancelled', 'unpaid', 'unprocessed', 'released'],
        ];
        $orders = collect();
        foreach ($states as $index => [$status, $paymentStatus, $fulfillmentStatus, $inventoryState]) {
            $customer = $customers[$index];
            $variant = $variants[$index];
            $quantity = ($index % 2) + 1;
            $subtotal = $variant->price * $quantity;
            $shippingCost = $status === 'cancelled' ? 0 : 18000;
            $discount = $index === 4 ? 50000 : 0;
            $order = Order::updateOrCreate(['number' => 'DTH-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)], [
                'checkout_key' => hash('sha256', 'dthree-demo-order-'.($index + 1)), 'user_id' => $customer->id,
                'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email,
                'address' => ['address' => 'Jl. Contoh No. '.($index + 10), 'district' => 'Kecamatan Demo', 'city' => ['Bandung', 'Jakarta Selatan', 'Tasikmalaya', 'Semarang', 'Surabaya', 'Yogyakarta', 'Medan'][$index], 'province' => ['Jawa Barat', 'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'DI Yogyakarta', 'Sumatera Utara'][$index], 'postal_code' => '401'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                'payment_method' => $index === 4 ? 'cod' : 'transfer', 'status' => $status, 'payment_status' => $paymentStatus,
                'fulfillment_status' => $fulfillmentStatus, 'subtotal' => $subtotal, 'shipping_cost' => $shippingCost,
                'discount' => $discount, 'shipping_discount' => 0, 'cod_fee' => $index === 4 ? 5000 : 0,
                'total' => $subtotal + $shippingCost + ($index === 4 ? 5000 : 0) - $discount,
                'shipping' => ['courier' => 'JNE', 'service' => 'REG', 'cost' => $shippingCost, 'estimate' => '2–3 hari'],
                'bank_accounts' => [['bank' => 'BANK DEMO', 'number' => '0000000000', 'owner' => 'DTHREE DEMO']],
                'notes' => 'Pesanan contoh untuk latihan operasional CMS.', 'tracking_number' => in_array($fulfillmentStatus, ['shipped', 'received'], true) ? 'JNEDEMO'.str_pad((string) $index, 8, '0', STR_PAD_LEFT) : null,
                'inventory_state' => $inventoryState, 'expires_at' => now()->addDay(),
                'paid_at' => $paymentStatus === 'paid' ? now()->subDays(max(1, 6 - $index)) : null,
                'shipped_at' => in_array($fulfillmentStatus, ['shipped', 'received'], true) ? now()->subDays($fulfillmentStatus === 'received' ? 4 : 1) : null,
                'completed_at' => $status === 'completed' ? now()->subDays(2) : null,
                'received_at' => $status === 'completed' ? now()->subDays(2) : null,
            ]);
            $item = OrderItem::updateOrCreate(['order_id' => $order->id, 'variant_id' => $variant->id], [
                'sku' => $variant->sku, 'name' => $variant->series->name, 'attributes' => ['color' => $variant->color, 'size' => $variant->size],
                'fulfillment' => $variant->fulfillment, 'lead_time' => $variant->lead_time, 'weight' => $variant->weight,
                'price' => $variant->price, 'quantity' => $quantity, 'total' => $subtotal,
            ]);
            if ($paymentStatus === 'review') {
                PaymentProof::updateOrCreate(['order_id' => $order->id], [
                    'path' => 'demo/payment-proof-demo-3x4.png',
                    'status' => 'pending',
                ]);
            }
            $event = OrderEvent::updateOrCreate(['event_key' => 'demo-order-'.$order->id.'-'.$status], [
                'order_id' => $order->id, 'type' => 'status_changed', 'message' => 'Status contoh: '.str_replace('_', ' ', $status).'.',
            ]);
            NotificationDelivery::updateOrCreate(['dedupe_key' => hash('sha256', 'demo-notification-'.$event->id)], [
                'order_event_id' => $event->id, 'channel' => 'email', 'recipient' => $customer->email,
                'payload' => ['subject' => 'Pembaruan pesanan '.$order->number, 'body' => $event->message, 'message' => $event->message],
                'status' => $index < 4 ? 'sent' : 'pending', 'attempts' => $index < 4 ? 1 : 0,
                'available_at' => now(), 'sent_at' => $index < 4 ? now()->subHours($index + 1) : null,
            ]);
            StockMovement::updateOrCreate(['variant_id' => $variant->id, 'order_id' => $order->id, 'reason' => 'demo_order'], [
                'user_id' => null, 'stock_delta' => in_array($status, ['cancelled', 'waiting'], true) ? 0 : -$quantity,
                'reserved_delta' => $status === 'waiting' ? $quantity : 0,
            ]);
            $orders->push([$order, $item]);
        }

        [$completedOrder, $completedItem] = $orders[5];
        $case = ServiceCase::updateOrCreate(['number' => 'CASE-DEMO-001'], [
            'request_key' => '00000000-0000-4000-8000-000000000101', 'order_id' => $completedOrder->id, 'type' => 'return',
            'reason' => 'Ukuran kurang sesuai dan pelanggan meminta penukaran.', 'reason_code' => 'size_issue', 'status' => 'reviewing',
            'resolution' => 'Contoh kasus sedang menunggu barang diterima tim gudang.',
            'policy_snapshot' => ['return_days' => 7, 'warranty_days' => 30, 'evidence_required' => true],
        ]);
        CaseItem::updateOrCreate(['service_case_id' => $case->id, 'order_item_id' => $completedItem->id], ['quantity' => 1, 'restocked_quantity' => 0]);
        CaseEvent::updateOrCreate(['service_case_id' => $case->id, 'type' => 'submitted'], ['user_id' => $completedOrder->user_id, 'message' => 'Pelanggan mengajukan penukaran ukuran.']);

        foreach ($orders as $index => [$order]) {
            AuditLog::updateOrCreate(['action' => 'demo.order.seeded', 'subject' => 'Order:'.$order->id], [
                'user_id' => null, 'before' => null, 'after' => ['number' => $order->number, 'status' => $order->status], 'ip' => '127.0.0.1',
            ]);
        }
    }

    private function seedAudienceAndMedia(): void
    {
        foreach ([
            ['newsletter1.demo@dthree.co.id', 'Aulia', 'footer'], ['newsletter2.demo@dthree.co.id', 'Nisa', 'popup'],
            ['newsletter3.demo@dthree.co.id', 'Rika', 'checkout'], ['newsletter4.demo@dthree.co.id', 'Mira', 'footer'],
            ['newsletter5.demo@dthree.co.id', 'Anisa', 'campaign'], ['newsletter6.demo@dthree.co.id', 'Putri', 'footer'],
        ] as $index => [$email, $name, $source]) {
            NewsletterSubscriber::updateOrCreate(['email' => $email], ['name' => $name, 'source' => $source, 'active' => true, 'subscribed_at' => now()->subDays(5 + ($index * 6))]);
        }
        $questionUser = User::where('email', 'alya.demo@dthree.co.id')->first();
        $questions = [
            ['raffah-signature', 'Apakah bahan menerawang jika dipakai di luar ruangan?', 'Tidak. Raffah menggunakan material berketebalan cukup; untuk warna terang kami tetap menyarankan inner.'],
            ['naura-pleated', 'Apakah bagian lipit mudah berubah setelah dicuci?', 'Gunakan pencucian lembut dan hindari memeras kuat agar bentuk lipit lebih terjaga.'],
            ['arcelia-family', 'Apakah anggota keluarga bisa membeli ukuran berbeda?', 'Bisa. Pilih jenis produk dan ukuran masing-masing pada varian yang tersedia.'],
            ['hana-flow-khimar', 'Apakah khimar ini nyaman untuk penggunaan berkacamata?', 'Ya, bahan ringan dan bukaan wajah dirancang cukup fleksibel untuk penggunaan kacamata.'],
        ];
        foreach ($questions as [$slug, $question, $answer]) {
            $series = Series::where('slug', $slug)->firstOrFail();
            ProductQuestion::updateOrCreate(['series_id' => $series->id, 'user_id' => $questionUser->id, 'question' => $question], [
                'answer' => $answer, 'status' => 'answered', 'answered_by' => null, 'answered_at' => now()->subDay(),
            ]);
        }
        foreach (glob(database_path('seeders/assets/demo/*.*')) ?: [] as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($extension, ['png', 'webp'], true)) continue;
            $name = pathinfo($file, PATHINFO_FILENAME);
            MediaAsset::updateOrCreate(['path' => 'demo/'.basename($file)], [
                'user_id' => null, 'name' => str($name)->replace('-', ' ')->title()->toString(),
                'mime' => 'image/'.$extension, 'size' => filesize($file),
                'alt_text' => 'Aset demonstrasi '.str($name)->replace('-', ' ')->toString(), 'active' => true,
            ]);
        }
    }

    private function seedReviews(): void
    {
        $customer = User::updateOrCreate(['email' => 'pelanggan.demo@dthree.co.id'], [
            'name' => 'Pelanggan Dthree',
            'phone' => '6281234567890',
            'password' => Str::random(64),
        ]);
        $order = Order::updateOrCreate(['number' => 'DEMO-REVIEW-001'], [
            'checkout_key' => 'demo-review-order-001',
            'user_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => ['address' => 'Alamat demonstrasi', 'city' => 'Tasikmalaya', 'province' => 'Jawa Barat', 'postal_code' => '46100'],
            'payment_method' => 'transfer',
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'received',
            'subtotal' => 0,
            'shipping_cost' => 0,
            'total' => 0,
            'shipping' => ['courier' => 'JNE', 'service' => 'REG', 'cost' => 0],
            'inventory_state' => 'committed',
            'expires_at' => now()->addDay(),
            'paid_at' => now()->subDays(8),
            'shipped_at' => now()->subDays(7),
            'completed_at' => now()->subDays(4),
            'received_at' => now()->subDays(4),
        ]);

        foreach (Series::whereIn('slug', collect($this->products())->pluck('slug'))->with('variants')->get() as $index => $series) {
            $variant = $series->variants->first();
            if (! $variant) {
                continue;
            }
            $item = OrderItem::updateOrCreate(['order_id' => $order->id, 'variant_id' => $variant->id], [
                'sku' => $variant->sku,
                'name' => $series->name,
                'attributes' => ['color' => $variant->color, 'size' => $variant->size],
                'fulfillment' => $variant->fulfillment,
                'lead_time' => $variant->lead_time,
                'weight' => $variant->weight,
                'price' => $variant->price,
                'quantity' => 1,
                'total' => $variant->price,
            ]);
            Review::updateOrCreate(['order_item_id' => $item->id], [
                'user_id' => $customer->id,
                'variant_id' => $variant->id,
                'rating' => 4 + ($index % 2),
                'body' => $index % 2 ? 'Potongannya rapi, bahannya nyaman, dan warnanya sesuai foto.' : 'Nyaman dipakai seharian dan ukuran sesuai panduan.',
                'status' => 'approved',
                'moderated_at' => now(),
            ]);
        }
        $reviewSubtotal = (int) $order->items()->sum('total');
        $order->update(['subtotal' => $reviewSubtotal, 'total' => $reviewSubtotal]);
    }

    private function products(): array
    {
        return [
            ['name' => 'Raffah Signature', 'slug' => 'raffah-signature', 'code' => 'RAF', 'category' => 'abaya-dewasa', 'description' => 'Abaya berpotongan A-line dengan detail minimal yang memberi kesan tenang dan berkelas.', 'material' => 'Nidha premium yang jatuh, ringan, dan tidak menerawang.', 'price' => 389000, 'image' => 'product-olive-4x5.png', 'gallery' => ['product-olive-4x5.png', 'product-black-4x5.png', 'product-rose-4x5.png'], 'colors' => ['Olive', 'Black', 'Sand'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Naura Pleated', 'slug' => 'naura-pleated', 'code' => 'NAU', 'category' => 'abaya-dewasa', 'description' => 'Abaya feminin dengan aksen lipit halus untuk tampilan lembut sepanjang hari.', 'material' => 'Ceruty baby doll berlapis furing premium.', 'price' => 429000, 'image' => 'product-rose-4x5.png', 'gallery' => ['product-rose-4x5.png', 'product-olive-4x5.png', 'product-black-4x5.png'], 'colors' => ['Dusty Rose', 'Mauve', 'Ivory'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Mahira Luxe', 'slug' => 'mahira-luxe', 'code' => 'MAH', 'category' => 'abaya-dewasa', 'description' => 'Abaya hitam premium dengan bordir tonal yang elegan untuk acara formal.', 'material' => 'Nidha Arab premium dengan bordir eksklusif.', 'price' => 549000, 'image' => 'product-black-4x5.png', 'gallery' => ['product-black-4x5.png', 'product-olive-4x5.png', 'product-rose-4x5.png'], 'colors' => ['Black', 'Midnight Navy', 'Mocha'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Alesha Everyday', 'slug' => 'alesha-everyday', 'code' => 'ALE', 'category' => 'abaya-dewasa', 'description' => 'Abaya praktis dengan potongan bersih untuk menemani aktivitas sehari-hari.', 'material' => 'Madina silk yang adem dan mudah dirawat.', 'price' => 359000, 'image' => 'product-olive-4x5.png', 'gallery' => ['product-olive-4x5.png', 'product-rose-4x5.png', 'product-black-4x5.png'], 'colors' => ['Sage', 'Taupe', 'Black'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Arcelia Family', 'slug' => 'arcelia-family', 'code' => 'ARC', 'category' => 'sarimbit-keluarga', 'description' => 'Koleksi keluarga bernuansa sage yang segar, hangat, dan mudah dipadukan.', 'material' => 'Katun dobby premium dengan aksen bordir lembut.', 'price' => 249000, 'image' => 'product-family-sage-4x5.png', 'gallery' => ['product-family-sage-4x5.png', 'product-olive-4x5.png', 'product-rose-4x5.png'], 'colors' => ['Sage', 'Warm Beige'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['Gamis Ibu', 'Koko Ayah', 'Koko Anak', 'Gamis Anak']],
            ['name' => 'Sakina Family', 'slug' => 'sakina-family', 'code' => 'SAK', 'category' => 'sarimbit-keluarga', 'description' => 'Sarimbit plum dan taupe yang menghadirkan kesan hangat dalam setiap kebersamaan.', 'material' => 'Katun jacquard lembut dengan detail bordir senada.', 'price' => 279000, 'image' => 'product-family-sage-4x5.png', 'gallery' => ['product-family-sage-4x5.png', 'product-rose-4x5.png', 'product-olive-4x5.png'], 'colors' => ['Plum', 'Warm Taupe'], 'sizes' => ['M', 'L', 'XL'], 'types' => ['Gamis Ibu', 'Koko Ayah', 'Koko Anak', 'Gamis Anak']],
            ['name' => 'Harmoni Raya', 'slug' => 'harmoni-raya', 'code' => 'HAR', 'category' => 'sarimbit-keluarga', 'description' => 'Set keluarga bernuansa netral untuk silaturahmi dan perayaan yang berkesan.', 'material' => 'Katun linen premium yang breathable dan bertekstur rapi.', 'price' => 299000, 'image' => 'product-family-sage-4x5.png', 'gallery' => ['product-family-sage-4x5.png', 'product-olive-4x5.png', 'product-black-4x5.png'], 'colors' => ['Soft Sage', 'Champagne'], 'sizes' => ['M', 'L', 'XL'], 'types' => ['Gamis Ibu', 'Koko Ayah', 'Koko Anak', 'Gamis Anak']],
            ['name' => 'Zahara Kids', 'slug' => 'zahara-kids', 'code' => 'ZAH', 'category' => 'busana-anak', 'description' => 'Gamis anak ringan dengan detail manis dan ruang gerak yang nyaman.', 'material' => 'Katun rayon lembut dan menyerap keringat.', 'price' => 229000, 'image' => 'product-rose-4x5.png', 'gallery' => ['product-rose-4x5.png', 'product-family-sage-4x5.png', 'product-olive-4x5.png'], 'colors' => ['Plum', 'Sage', 'Dusty Rose'], 'sizes' => ['4', '6', '8', '10', '12'], 'types' => ['']],
            ['name' => 'Livia Layer Outer', 'slug' => 'livia-layer-outer', 'code' => 'LIV', 'category' => 'outer-khimar', 'description' => 'Outer panjang berpotongan ringan yang memberi lapisan elegan tanpa terasa berat.', 'material' => 'Woolpeach premium yang ringan dan tidak mudah kusut.', 'price' => 319000, 'image' => 'product-olive-4x5.png', 'gallery' => ['product-olive-4x5.png', 'product-black-4x5.png', 'product-rose-4x5.png'], 'colors' => ['Olive', 'Black', 'Beige'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Hana Flow Khimar', 'slug' => 'hana-flow-khimar', 'code' => 'HAN', 'category' => 'outer-khimar', 'description' => 'Khimar instan berpotongan flowy dengan coverage nyaman dan tampilan rapi.', 'material' => 'Diamond crepe ringan, tidak licin, dan mudah dibentuk.', 'price' => 169000, 'image' => 'product-rose-4x5.png', 'gallery' => ['product-rose-4x5.png', 'product-black-4x5.png', 'product-olive-4x5.png'], 'colors' => ['Mauve', 'Black', 'Olive', 'Ivory'], 'sizes' => ['M', 'L', 'XL'], 'types' => ['']],
        ];
    }

    private function seedVariants(Series $series, array $product): void
    {
        foreach ($product['types'] as $typeIndex => $type) {
            foreach ($product['colors'] as $colorIndex => $color) {
                foreach ($product['sizes'] as $sizeIndex => $size) {
                    $typeCode = match ($type) {
                        'Gamis Ibu' => 'GIB',
                        'Koko Ayah' => 'KAY',
                        'Koko Anak' => 'KAN',
                        'Gamis Anak' => 'GAN',
                        default => 'STD',
                    };
                    $colorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', mb_substr($color, 0, 3)));
                    $sku = 'DEMO-'.$product['code'].'-'.$typeCode.'-'.$colorCode.'-'.$size;
                    $price = $product['price'] + ($typeIndex * 20000) + ($sizeIndex >= 2 ? 10000 : 0);

                    Variant::updateOrCreate(['sku' => $sku], [
                        'series_id' => $series->id,
                        'product_type' => $type,
                        'color' => $color,
                        'size' => $size,
                        'price' => $price,
                        'stock' => 6 + (abs(crc32($sku)) % 25),
                        'reserved' => 0,
                        'low_stock' => 5,
                        'weight' => in_array($type, ['Koko Anak', 'Gamis Anak'], true) ? 350 : 650,
                        'fulfillment' => 'ready_stock',
                        'lead_time' => null,
                        'active' => true,
                    ]);
                }
            }
        }
    }

    private function seedMedia(Series $series, array $product): void
    {
        $wantedImages = collect($product['gallery'])->map(fn ($image) => 'demo/'.$image)->all();
        ProductMedia::where('series_id', $series->id)->where('image', 'like', 'demo/%')->whereNotIn('image', $wantedImages)->delete();
        foreach ($product['gallery'] as $sort => $image) {
            $color = $product['colors'][$sort] ?? null;
            ProductMedia::updateOrCreate(
                ['series_id' => $series->id, 'image' => 'demo/'.$image],
                ['variant_id' => null, 'color' => $color, 'alt_text' => $product['name'].($color ? ' warna '.$color : '').' — foto '.($sort + 1), 'sort_order' => $sort, 'active' => true]
            );
        }
    }

    private function publishImages(): void
    {
        foreach (glob(database_path('seeders/assets/demo/*.*')) ?: [] as $source) {
            if (! in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['png', 'webp'], true)) continue;
            Storage::disk('public')->put('demo/'.basename($source), file_get_contents($source));
        }
    }

    private function seedShippingRates(): void
    {
        foreach ([
            ['JNE', 'REG', 'DKI Jakarta', 'Jakarta', 18000, 10000, '2–3 hari', true],
            ['JNE', 'YES', 'DKI Jakarta', 'Jakarta', 30000, 18000, '1 hari', false],
            ['JNE', 'REG', 'Jawa Barat', 'Bandung', 16000, 9000, '2–3 hari', true],
            ['JNE', 'REG', 'Jawa Barat', 'Tasikmalaya', 15000, 9000, '1–2 hari', true],
            ['POS', 'Kilat Khusus', 'Jawa Tengah', 'Semarang', 22000, 12000, '3–5 hari', true],
            ['POS', 'Kilat Khusus', 'Jawa Timur', 'Surabaya', 25000, 14000, '3–5 hari', true],
        ] as [$courier, $service, $province, $city, $firstKg, $extraKg, $estimate, $cod]) {
            ShippingRate::updateOrCreate(
                compact('courier', 'service', 'province', 'city'),
                ['first_kg' => $firstKg, 'extra_kg' => $extraKg, 'estimate' => $estimate, 'cod' => $cod, 'active' => true]
            );
        }
    }

    private function seedStoreLocations(): void
    {
        foreach ([
            ['Dthree Tasikmalaya', 'Jl. HZ Mustofa No. 88', 'Tasikmalaya', 'Setiap hari · 09.00–20.00', 1],
            ['Dthree Bandung', 'Jl. Buah Batu No. 121', 'Bandung', 'Senin–Sabtu · 09.00–19.00', 2],
            ['Dthree Jakarta', 'Jl. Kemang Raya No. 24', 'Jakarta Selatan', 'Setiap hari · 10.00–21.00', 3],
        ] as [$name, $address, $city, $hours, $sortOrder]) {
            StoreLocation::updateOrCreate(['name' => $name], [
                'address' => $address,
                'city' => $city,
                'map_url' => 'https://maps.google.com/?q='.urlencode($address.' '.$city),
                'hours' => $hours,
                'sort_order' => $sortOrder,
                'active' => true,
            ]);
        }
    }

    private function seedCuratedLinks(): void
    {
        foreach ([
            ['fit', 'Daily Wear', '/koleksi?sort=featured', null, null, 1],
            ['fit', 'Office Wear', '/koleksi?category=abaya-dewasa', null, null, 2],
            ['fit', 'Special Occasion', '/koleksi?category=sarimbit-keluarga', null, null, 3],
            ['fit', 'Earth Tone Collection', '/koleksi?color=Olive', null, null, 4],
            ['promo', 'New Arrival', '/koleksi?sort=newest', 'NEW ARRIVAL', 'demo/promo-taupe-3x4.png', 1],
            ['promo', 'Best Seller', '/koleksi?sort=best_selling', 'BEST SELLER', 'demo/promo-taupe-3x4.png', 2],
        ] as [$section, $title, $url, $eyebrow, $image, $sortOrder]) {
            CuratedLink::updateOrCreate(['section' => $section, 'title' => $title], compact('url', 'eyebrow', 'image') + ['sort_order' => $sortOrder, 'active' => true]);
        }
    }

    private function seedContent(): void
    {
        $entries = [
            ['banner', 'Kebersamaan dalam satu warna', 'demo-banner', 'Temukan koleksi sarimbit keluarga dengan bahan nyaman dan warna yang dirancang untuk saling melengkapi.', 'hero-family-2x1.png', '/koleksi?category=sarimbit-keluarga', 'Lihat sarimbit', 1],
            ['banner', 'Siluet yang bergerak bersama Anda', 'demo-banner-abaya', 'Koleksi abaya ringan dengan detail bersih untuk rutinitas dari pagi hingga malam.', 'hero-family-2x1.png', '/koleksi?category=abaya-dewasa', 'Lihat abaya', 2],
            ['banner', 'Warna lembut untuk hari istimewa', 'demo-banner-new', 'Temukan pilihan terbaru dalam palet hangat yang mudah dipadukan.', 'hero-family-2x1.png', '/koleksi?sort=newest', 'Lihat koleksi baru', 3],
            ['home_section', 'Dirancang untuk bergerak bersama Anda', 'demo-cerita', 'Material pilihan, potongan nyaman, dan detail yang tetap rapi dari pagi hingga malam.', 'editorial-fabrics-3x2.png', '/koleksi?category=abaya-dewasa', 'Jelajahi abaya', 2],
            ['home_section', 'Warna hangat untuk momen istimewa', 'demo-harmoni', 'Padukan koleksi dewasa dan anak untuk menciptakan tampilan keluarga yang selaras.', 'look-olive-beige-4x5.png', '/koleksi?category=sarimbit-keluarga', 'Pilih koleksi', 3],
            ['home_section', 'Siluet ringan dalam warna natural', 'demo-look-natural', 'Temukan abaya dan pelengkap dalam palet natural untuk gaya yang mudah dipadukan.', 'look-olive-beige-4x5.png', '/koleksi?category=abaya-dewasa', 'Lihat tampilan', 4],
            ['menu', 'FAQ', 'demo-menu-faq', null, null, '/konten/faq/pertanyaan-umum', 'FAQ', 3],
            ['menu', 'Journal', 'demo-menu-journal', null, null, '/konten/journal/merawat-busana', 'Journal', 2],
            ['menu', 'Lookbook', 'demo-menu-lookbook', null, null, '/konten/lookbook', 'Lookbook', 1],
            ['faq', 'Bagaimana memilih ukuran yang tepat?', 'pertanyaan-umum', 'Gunakan panduan ukuran pada halaman produk. Ukur lingkar dada pada bagian terlebar dan pilih ukuran dengan ruang longgar 4–8 cm sesuai preferensi.', null, null, null, 1],
            ['faq', 'Apakah warna produk sama dengan foto?', 'akurasi-warna', 'Kami berusaha menampilkan warna seakurat mungkin. Sedikit perbedaan dapat terjadi karena pencahayaan foto dan pengaturan layar.', null, null, null, 2],
            ['faq', 'Berapa lama pesanan diproses?', 'waktu-proses', 'Produk ready stock diproses 1–2 hari kerja setelah pembayaran terverifikasi. Estimasi pengiriman mengikuti layanan kurir.', null, null, null, 3],
            ['journal', 'Cara merawat busana agar tetap nyaman', 'merawat-busana', 'Pisahkan warna gelap dan terang, gunakan deterjen lembut, hindari pemutih, dan keringkan di tempat teduh agar serat serta warna lebih terjaga.', 'editorial-fabrics-3x2.png', null, null, 1],
            ['journal', 'Panduan memilih sarimbit keluarga', 'memilih-sarimbit', 'Mulai dari palet warna yang disukai, tentukan kebutuhan tiap anggota keluarga, lalu cocokkan ukuran melalui panduan pada setiap produk.', 'editorial-fabrics-3x2.png', null, null, 2],
            ['journal', 'Tiga cara memadukan abaya', 'memadukan-abaya', 'Gunakan aksesori bertekstur lembut, pilih alas kaki bernuansa netral, dan tambahkan outer ringan untuk menciptakan tampilan berbeda.', 'editorial-fabrics-3x2.png', null, null, 3],
            ['lookbook', 'The Warm Gathering', 'warm-gathering', 'Inspirasi busana keluarga dalam palet plum, sage, dan warna netral untuk momen kebersamaan.', 'look-olive-beige-4x5.png', '/koleksi?category=sarimbit-keluarga', 'Lihat koleksi', 1],
            ['lookbook', 'Everyday Modest Edit', 'everyday-modest-edit', 'Siluet ringan dan warna mudah dipadukan untuk rutinitas dari pagi hingga malam.', 'look-olive-beige-4x5.png', '/koleksi?category=abaya-dewasa', 'Lihat koleksi', 2],
            ['campaign', 'Hadiah pelanggan baru', 'promo-demo', 'Gunakan kode DEMO10 untuk potongan 10% pada transaksi demonstrasi pertama Anda.', 'promo-taupe-3x4.png', '/koleksi', 'Mulai belanja', 1],
            ['campaign', 'Lengkapi gaya keluarga', 'promo-family', 'Gunakan FAMILY15 untuk potongan 15% pada pembelian koleksi sarimbit dengan minimum transaksi yang berlaku.', 'promo-taupe-3x4.png', '/koleksi?category=sarimbit-keluarga', 'Lihat koleksi', 2],
        ];

        foreach ($entries as [$type, $title, $slug, $summary, $image, $url, $label, $sort]) {
            ContentEntry::updateOrCreate(['slug' => $slug], [
                'type' => $type,
                'title' => $title,
                'summary' => $summary,
                'body' => $summary,
                'image' => $image ? 'demo/'.$image : null,
                'link_url' => $url,
                'link_label' => $label,
                'seo_title' => $title.' — Dthree',
                'seo_description' => $summary,
                'sort_order' => $sort,
                'editorial_status' => 'approved',
                'active' => true,
            ]);
        }

        $lookProducts = Series::whereIn('slug', ['sakina-family', 'arcelia-family', 'hana-flow-khimar'])->pluck('id', 'slug');
        ContentEntry::where('slug', 'demo-harmoni')->update(['blocks' => [
            ['type' => 'product_hotspot', 'series_id' => $lookProducts['sakina-family'], 'x' => 33, 'y' => 55],
            ['type' => 'product_hotspot', 'series_id' => $lookProducts['arcelia-family'], 'x' => 62, 'y' => 44],
            ['type' => 'product_hotspot', 'series_id' => $lookProducts['hana-flow-khimar'], 'x' => 78, 'y' => 24],
        ]]);
        ContentEntry::where('slug', 'demo-look-natural')->update(['blocks' => [
            ['type' => 'product_hotspot', 'series_id' => $lookProducts['arcelia-family'], 'x' => 30, 'y' => 48],
            ['type' => 'product_hotspot', 'series_id' => $lookProducts['hana-flow-khimar'], 'x' => 67, 'y' => 36],
        ]]);
        ContentEntry::where('slug', 'demo-banner')->update(['blocks' => [[
            'type' => 'hero_options',
            'align' => 'left',
            'tone' => 'light',
            'duration' => 6500,
            'mobile_url' => '',
        ]]]);
        ContentEntry::where('slug', 'demo-banner-abaya')->update(['blocks' => [['type' => 'hero_options', 'align' => 'right', 'tone' => 'light', 'duration' => 6000, 'mobile_url' => '']]]);
        ContentEntry::where('slug', 'demo-banner-new')->update(['blocks' => [['type' => 'hero_options', 'align' => 'center', 'tone' => 'light', 'duration' => 6000, 'mobile_url' => '']]]);
    }

    private function seedCoupons(): void
    {
        foreach ([
            ['DEMO10', 'percent', 10, 200000, 50000, 'all', 'all'],
            ['FAMILY15', 'percent', 15, 750000, 150000, 'all', 'category'],
            ['HEMAT50', 'fixed', 50000, 500000, 50000, 'all', 'all'],
        ] as [$code, $type, $value, $minimum, $maximum, $segment, $targetType]) {
            Coupon::updateOrCreate(['code' => $code], [
                'type' => $type,
                'value' => $value,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'usage_limit' => 1000,
                'starts_at' => now()->subYear(),
                'ends_at' => now()->addYears(2),
                'active' => true,
                'segment' => $segment,
                'target_type' => $targetType,
                'per_user_limit' => 3,
            ]);
        }
    }

    private function seedSettings(): void
    {
        foreach ([
            'demo_mode' => true,
            'checkout_enabled' => true,
            'cod_enabled' => true,
            'loyalty_enabled' => true,
            'analytics_enabled' => true,
            'brand_name' => 'Dthree',
            'announcement_lines' => "GRATIS ONGKIR UNTUK PROMO TERPILIH\nDAPATKAN 10% DENGAN KODE DEMO10\nPENGIRIMAN KE SELURUH INDONESIA",
            'announcement_interval_seconds' => 4,
            'welcome_voucher_enabled' => true,
            'welcome_voucher_title' => 'Hadiah untuk pembelian pertama',
            'welcome_voucher_text' => 'Nikmati potongan 10% untuk koleksi pilihan dengan minimum transaksi yang berlaku.',
            'welcome_voucher_code' => 'DEMO10',
            'free_shipping_threshold' => 500000,
            'hero_eyebrow' => 'Koleksi terbaru Dthree',
            'hero_title' => 'Tumbuh bersama dalam setiap cerita',
            'hero_text' => 'Busana modest yang nyaman untuk perempuan dan keluarga Indonesia, dirancang dengan warna hangat serta detail yang berkesan.',
            'hero_button_label' => 'Belanja koleksi',
            'hero_button_url' => '/koleksi',
            'hero_image' => 'demo/hero-family-2x1.png',
            'benefit_1' => 'Bahan nyaman pilihan',
            'benefit_2' => 'Pengiriman ke seluruh Indonesia',
            'benefit_3' => 'Retur mudah sesuai ketentuan',
            'story_eyebrow' => 'Tentang Dthree',
            'story_title' => 'Busana yang menyertai momen keluarga',
            'story_text' => 'Kami menghadirkan koleksi dengan siluet santun, warna mudah dipadukan, dan kenyamanan yang mendukung aktivitas Anda.',
            'hero_height_desktop' => 633,
            'hero_autoplay_seconds' => 6.5,
            'theme_font' => 'poppins',
            'header_sticky' => true,
            'storefront_animation_speed' => 550,
            'product_grid_columns' => 4,
            'home_featured_enabled' => true,
            'home_categories_enabled' => true,
            'home_collections_enabled' => true,
            'home_look_enabled' => true,
            'home_sale_enabled' => true,
            'product_related_enabled' => true,
            'cart_recommendations_enabled' => true,
            'home_editorial_sections_enabled' => true,
            'home_story_enabled' => true,
            'home_featured_slug' => 'raffah-signature',
            'home_category_slugs' => "raffah-signature\narcelia-family\nzahara-kids",
            'home_new_slugs' => "naura-pleated, alesha-everyday, hana-flow-khimar, zahara-kids",
            'home_best_slugs' => "raffah-signature, mahira-luxe, arcelia-family, sakina-family",
            'home_sale_slugs' => "raffah-signature, naura-pleated, harmoni-raya, livia-layer-outer",
            'store_location' => 'Tasikmalaya · Indonesia',
            'contact_phone' => '0812-0000-0000',
            'contact_email' => 'hello@dthree.co.id',
            'bank_accounts' => [['bank' => 'BANK DEMO', 'number' => '0000000000', 'owner' => 'DTHREE DEMO']],
            'loyalty_tiers' => [
                ['name' => 'Member', 'minimum' => 0, 'benefit' => 'Kumpulkan poin di setiap transaksi', 'value_bonus_percent' => 0],
                ['name' => 'Sahabat', 'minimum' => 500, 'benefit' => 'Nilai penukaran poin lebih besar', 'value_bonus_percent' => 10],
                ['name' => 'Keluarga', 'minimum' => 1500, 'benefit' => 'Prioritas promo dan bonus poin', 'value_bonus_percent' => 20],
            ],
        ] as $key => $value) {
            Setting::put($key, $value);
        }
    }

    private function seedPages(): void
    {
        $copy = [
            'syarat-ketentuan' => 'Dengan melakukan pemesanan, pelanggan menyetujui detail produk, harga, metode pembayaran, serta kebijakan pengiriman yang ditampilkan saat checkout.',
            'privasi' => 'Dthree menggunakan data pelanggan hanya untuk memproses pesanan, memberi layanan pelanggan, dan menjalankan fitur akun sesuai persetujuan.',
            'pengiriman' => 'Pesanan ready stock diproses 1–2 hari kerja. Estimasi tiba mengikuti layanan kurir dan lokasi tujuan yang dipilih saat checkout.',
            'retur-garansi' => 'Pengajuan retur dapat dilakukan sesuai batas waktu dan kondisi produk pada kebijakan toko. Simpan bukti pembelian dan dokumentasi saat paket diterima.',
            'tentang' => 'Dthree adalah brand modest fashion dari Tasikmalaya yang menghadirkan busana perempuan dan keluarga dengan fokus pada kenyamanan, detail, dan kebersamaan.',
            'sejarah' => 'Dthree tumbuh dari Tasikmalaya dengan tujuan menghadirkan busana modest yang dekat dengan keseharian perempuan dan keluarga Indonesia.',
            'cara-pemesanan' => 'Pilih series, warna, dan ukuran yang tersedia. Tambahkan ke keranjang, lengkapi alamat, pilih pengiriman serta pembayaran, lalu tinjau pesanan sebelum konfirmasi.',
            'pengiriman-internasional' => 'Pengiriman internasional dilayani melalui konfirmasi tim Dthree. Hubungi layanan pelanggan untuk pemeriksaan negara tujuan, ongkir, dan estimasi.',
        ];

        foreach ($copy as $slug => $body) {
            $title = str($slug)->replace('-', ' ')->title()->toString();
            $page = Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'active' => true]);
            $page->update(['active' => true, 'body' => $body, 'editorial_status' => 'approved', 'seo_title' => $page->title.' — Dthree', 'seo_description' => $body]);
        }
    }
}
