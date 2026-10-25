<?php

use App\Models\BundleComponent;
use App\Models\BusinessOrderRule;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\MeasurementTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\PriceGroup;
use App\Models\ProductMedia;
use App\Models\Series;
use App\Models\ShippingRate;
use App\Models\StoreLocation;
use App\Models\Variant;
use App\Models\VariantPrice;

// field: label, input type, validation, optional select options.
return [
    'product-media' => ['model' => ProductMedia::class, 'title' => 'Galeri produk', 'permission' => 'catalog.write', 'columns' => ['series_id' => 'Produk / series', 'variant_id' => 'SKU khusus', 'color' => 'Warna', 'alt_text' => 'Alt text', 'sort_order' => 'Urutan', 'active' => 'Aktif'], 'fields' => [
        'series_id' => ['Series', 'series', 'required|exists:series,id'], 'variant_id' => ['SKU khusus (opsional)', 'variant', 'nullable|exists:variants,id'], 'color' => ['Warna khusus (opsional)', 'text', 'nullable|string|max:80'],
        'image' => ['Gambar', 'file', 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072'], 'alt_text' => ['Alt text', 'text', 'required|string|max:200'], 'sort_order' => ['Urutan', 'number', 'required|integer|min:0|max:10000'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'content' => ['model' => ContentEntry::class, 'title' => 'Banner & konten website', 'permission' => 'content.write', 'columns' => ['type' => 'Tipe', 'title' => 'Judul', 'slug' => 'Slug', 'editorial_status' => 'Status konten', 'sort_order' => 'Urutan', 'starts_at' => 'Mulai', 'active' => 'Terbit'], 'fields' => [
        'type' => ['Tipe konten', 'select', 'required|in:banner,home_section,menu,faq,journal,lookbook,campaign', ['banner' => 'Banner', 'home_section' => 'Section beranda / Shop the Look', 'menu' => 'Menu', 'faq' => 'FAQ', 'journal' => 'Journal', 'lookbook' => 'Lookbook', 'campaign' => 'Campaign']],
        'title' => ['Judul', 'text', 'required|string|max:200'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:180'], 'summary' => ['Ringkasan', 'textarea', 'nullable|string|max:2000'], 'body' => ['Isi teks cadangan', 'textarea', 'nullable|string|max:50000'], 'template' => ['Template halaman', 'select', 'sometimes|in:standard,wide,landing', ['standard' => 'Standar', 'wide' => 'Lebar', 'landing' => 'Landing page']], 'blocks' => ['Blok konten', 'blocks', 'nullable|string|max:50000'],
        'image' => ['Gambar', 'file', 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072'], 'link_url' => ['Tautan', 'text', 'nullable|string|max:500'], 'link_label' => ['Label tautan', 'text', 'nullable|string|max:100'],
        'seo_title' => ['Judul SEO', 'text', 'nullable|string|max:200'], 'seo_description' => ['Deskripsi SEO', 'textarea', 'nullable|string|max:300'], 'sort_order' => ['Urutan', 'number', 'required|integer|min:0|max:10000'],
        'editorial_status' => ['Status editorial', 'select', 'required|in:draft,review,approved', ['draft' => 'Draft', 'review' => 'Menunggu review', 'approved' => 'Disetujui']], 'review_note' => ['Catatan editor/reviewer', 'textarea', 'nullable|string|max:1000'],
        'starts_at' => ['Mulai tayang', 'datetime-local', 'nullable|date'], 'ends_at' => ['Selesai tayang', 'datetime-local', 'nullable|date|after:starts_at'], 'active' => ['Tayangkan setelah disetujui', 'checkbox', 'boolean']]],
    'bundle-components' => ['model' => BundleComponent::class, 'title' => 'Komponen bundling', 'permission' => 'catalog.write', 'columns' => ['bundle_variant_id' => 'SKU bundle ID', 'component_variant_id' => 'SKU komponen ID', 'quantity' => 'Jumlah komponen'], 'fields' => [
        'bundle_variant_id' => ['SKU bundle', 'variant', 'required|exists:variants,id'], 'component_variant_id' => ['SKU komponen', 'variant', 'required|exists:variants,id'],
        'quantity' => ['Jumlah komponen per bundle', 'number', 'required|integer|min:1|max:1000']]],
    'measurement-templates' => ['model' => MeasurementTemplate::class, 'title' => 'Template ukuran custom', 'permission' => 'catalog.write', 'columns' => ['name' => 'Template', 'product_type' => 'Jenis produk', 'pricing_mode' => 'Harga', 'surcharge' => 'Biaya custom', 'lead_time' => 'Estimasi', 'active' => 'Aktif'], 'fields' => [
        'name' => ['Nama template', 'text', 'required|string|max:150'], 'product_type' => ['Jenis produk (opsional)', 'text', 'nullable|string|max:100'],
        'pricing_mode' => ['Mekanisme harga', 'select', 'required|in:fixed,quote', ['fixed' => 'Biaya tetap', 'quote' => 'Penawaran admin']],
        'surcharge' => ['Biaya custom tetap (Rupiah)', 'number', 'nullable|integer|min:0|max:100000000'], 'lead_time' => ['Estimasi produksi', 'text', 'required|string|max:150'],
        'fields' => ['Field ukuran (JSON)', 'textarea', 'required|string|max:20000'], 'policy' => ['Petunjuk dan kebijakan', 'textarea', 'nullable|string|max:5000'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'price-groups' => ['model' => PriceGroup::class, 'title' => 'Grup harga bisnis', 'permission' => 'catalog.write', 'columns' => ['name' => 'Grup', 'business_type' => 'Jenis bisnis', 'minimum_order_quantity' => 'Min. item', 'minimum_order_amount' => 'Min. nilai', 'active' => 'Aktif'], 'fields' => [
        'name' => ['Nama grup', 'text', 'required|string|max:100'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:100'],
        'business_type' => ['Jenis bisnis', 'select', 'required|in:reseller,dropshipper,wholesale', ['reseller' => 'Reseller', 'dropshipper' => 'Dropshipper', 'wholesale' => 'Grosir']],
        'minimum_order_quantity' => ['Minimum total item per pesanan', 'number', 'required|integer|min:1|max:10000'],
        'minimum_order_amount' => ['Minimum nilai pesanan (Rupiah)', 'number', 'required|integer|min:0|max:1000000000'],
        'discount_percent' => ['Diskon dasar dari harga retail (%)', 'number', 'required|integer|min:0|max:100'],
        'active' => ['Aktif', 'checkbox', 'boolean']]],
    'business-order-rules' => ['model' => BusinessOrderRule::class, 'title' => 'Minimum pembelian bertarget B2B', 'permission' => 'catalog.write', 'columns' => ['price_group_id' => 'Grup ID', 'target_type' => 'Target', 'target_id' => 'ID target', 'minimum_quantity' => 'Min. item', 'minimum_amount' => 'Min. nilai', 'active' => 'Aktif'], 'fields' => [
        'price_group_id' => ['Grup harga', 'price_group', 'required|exists:price_groups,id'], 'target_type' => ['Jenis target', 'select', 'required|in:category,series,variant', ['category' => 'Kategori', 'series' => 'Series', 'variant' => 'SKU']],
        'target_id' => ['ID target', 'number', 'required|integer|min:1'], 'minimum_quantity' => ['Minimum item target', 'number', 'required|integer|min:0|max:10000'], 'minimum_amount' => ['Minimum nilai target', 'number', 'required|integer|min:0|max:1000000000'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'variant-prices' => ['model' => VariantPrice::class, 'title' => 'Harga bertingkat bisnis', 'permission' => 'catalog.write', 'columns' => ['variant_id' => 'SKU ID', 'price_group_id' => 'Grup ID', 'minimum_quantity' => 'Mulai jumlah', 'price' => 'Harga/item', 'active' => 'Aktif'], 'fields' => [
        'variant_id' => ['SKU', 'variant', 'required|exists:variants,id'], 'price_group_id' => ['Grup harga', 'price_group', 'required|exists:price_groups,id'],
        'minimum_quantity' => ['Mulai jumlah item SKU', 'number', 'required|integer|min:1|max:10000'],
        'price' => ['Harga per item (Rupiah)', 'number', 'required|integer|min:1|max:100000000'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'categories' => ['model' => Category::class, 'title' => 'Kategori', 'permission' => 'catalog.write', 'columns' => ['name' => 'Kategori', 'slug' => 'Slug', 'active' => 'Aktif'], 'fields' => [
        'name' => ['Nama', 'text', 'required|string|max:100'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:100'], 'description' => ['Deskripsi kategori', 'textarea', 'nullable|string|max:5000'],
        'seo_title' => ['Judul SEO', 'text', 'nullable|string|max:200'], 'seo_description' => ['Deskripsi SEO', 'textarea', 'nullable|string|max:300'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'series' => ['model' => Series::class, 'title' => 'Produk / series', 'permission' => 'catalog.write', 'columns' => ['name' => 'Produk / series', 'slug' => 'Slug', 'active' => 'Aktif'], 'fields' => [
        'category_id' => ['Kategori', 'category', 'required|exists:categories,id'], 'name' => ['Nama series', 'text', 'required|string|max:150'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:150'],
        'description' => ['Deskripsi', 'textarea', 'nullable|string|max:10000'], 'material' => ['Bahan', 'textarea', 'nullable|string|max:2000'], 'care' => ['Perawatan', 'textarea', 'nullable|string|max:2000'],
        'seo_title' => ['Judul SEO', 'text', 'nullable|string|max:200'], 'seo_description' => ['Deskripsi SEO', 'textarea', 'nullable|string|max:300'],
        'size_guide' => ['Panduan ukuran', 'textarea', 'nullable|string|max:5000'], 'image' => ['Foto produk (JPG / PNG / WebP, maks. 3 MB)', 'file', 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072'],
        'uses_product_type' => ['Gunakan jenis produk (Sarimbit)', 'checkbox', 'boolean'], 'featured' => ['Tampilkan di beranda', 'checkbox', 'boolean'], 'active' => ['Terbitkan', 'checkbox', 'boolean']]],
    'variants' => ['model' => Variant::class, 'title' => 'Varian & SKU', 'permission' => 'catalog.write', 'columns' => ['sku' => 'SKU', 'product_type' => 'Jenis', 'color' => 'Warna', 'size' => 'Ukuran', 'price' => 'Harga', 'stock' => 'Stok / kuota', 'reserved' => 'Reservasi'], 'fields' => [
        'series_id' => ['Series', 'series', 'required|exists:series,id'], 'sku' => ['SKU manual', 'text', 'required|regex:/^[A-Za-z0-9._-]+$/|max:80'], 'product_type' => ['Jenis produk (kosong untuk Abaya)', 'text', 'nullable|string|max:100'],
        'color' => ['Warna', 'text', 'required|string|max:80'], 'size' => ['Ukuran', 'text', 'required|string|max:40'], 'price' => ['Harga (Rupiah)', 'number', 'required|integer|min:1|max:100000000'],
        'weight' => ['Berat (gram)', 'number', 'required|integer|min:1|max:100000'], 'low_stock' => ['Peringatan stok minimum', 'number', 'required|integer|min:0|max:100000'],
        'fulfillment' => ['Metode pemenuhan', 'select', 'required|in:ready_stock,pre_order,custom_made,bundle', ['ready_stock' => 'Ready stock', 'pre_order' => 'Pre-order', 'custom_made' => 'Ukuran custom', 'bundle' => 'Bundle SKU']],
        'measurement_template_id' => ['Template ukuran (wajib untuk custom)', 'measurement_template', 'nullable|exists:measurement_templates,id'],
        'lead_time' => ['Estimasi pemenuhan', 'text', 'nullable|string|max:150'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'shipping-rates' => ['model' => ShippingRate::class, 'title' => 'Tarif pengiriman manual', 'permission' => 'settings.write', 'columns' => ['courier' => 'Kurir', 'service' => 'Layanan', 'province' => 'Provinsi', 'city' => 'Kota', 'first_kg' => 'Kg pertama', 'cod' => 'COD'], 'fields' => [
        'courier' => ['Kurir', 'select', 'required|in:POS,JNE', ['POS' => 'POS', 'JNE' => 'JNE']], 'service' => ['Layanan', 'text', 'required|string|max:80'],
        'province' => ['Provinsi (* untuk semua)', 'text', 'required|string|max:100'], 'city' => ['Kota / kabupaten (* untuk semua)', 'text', 'required|string|max:100'],
        'first_kg' => ['Tarif kilogram pertama', 'number', 'required|integer|min:0|max:10000000'], 'extra_kg' => ['Tarif setiap kg berikutnya', 'number', 'required|integer|min:0|max:10000000'],
        'estimate' => ['Estimasi pengiriman', 'text', 'required|string|max:100'], 'cod' => ['Izinkan COD pada wilayah ini', 'checkbox', 'boolean'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'coupons' => ['model' => Coupon::class, 'title' => 'Voucher & promosi', 'permission' => 'promotions.write', 'columns' => ['code' => 'Kode', 'type' => 'Jenis', 'value' => 'Nilai', 'used' => 'Terpakai', 'usage_limit' => 'Kuota', 'active' => 'Aktif'], 'fields' => [
        'code' => ['Kode voucher', 'text', 'required|alpha_dash|max:40'], 'type' => ['Jenis', 'select', 'required|in:fixed,percent,shipping', ['fixed' => 'Potongan Rupiah', 'percent' => 'Persentase', 'shipping' => 'Subsidi ongkir']],
        'value' => ['Nilai potongan / persen', 'number', 'required|integer|min:1|max:100000000'], 'minimum' => ['Minimum belanja', 'number', 'required|integer|min:0|max:100000000'],
        'maximum' => ['Maksimum subsidi (Rupiah)', 'number', 'nullable|integer|min:0|max:100000000'], 'usage_limit' => ['Kuota pemakaian', 'number', 'required|integer|min:1|max:1000000'],
        'segment' => ['Segmen pelanggan', 'select', 'required|in:all,guest,customer,business', ['all' => 'Semua', 'guest' => 'Tamu', 'customer' => 'Akun retail', 'business' => 'Akun bisnis']], 'price_group_id' => ['Grup harga khusus (opsional)', 'price_group', 'nullable|exists:price_groups,id'],
        'target_type' => ['Target item', 'select', 'required|in:all,category,series,variant', ['all' => 'Semua item', 'category' => 'Kategori', 'series' => 'Series', 'variant' => 'SKU']], 'target_id' => ['ID target (kosong jika semua)', 'number', 'nullable|integer|min:1'],
        'combinable' => ['Boleh digabung voucher lain', 'checkbox', 'boolean'], 'priority' => ['Prioritas', 'number', 'required|integer|min:0|max:10000'], 'per_user_limit' => ['Batas per akun', 'number', 'nullable|integer|min:1|max:10000'],
        'starts_at' => ['Mulai (WIB)', 'datetime-local', 'required|date'], 'ends_at' => ['Selesai (WIB)', 'datetime-local', 'required|date|after:starts_at'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'pages' => ['model' => Page::class, 'title' => 'Halaman informasi & kebijakan', 'permission' => 'content.write', 'columns' => ['title' => 'Judul', 'slug' => 'Slug', 'editorial_status' => 'Status konten', 'active' => 'Terbit'], 'fields' => [
        'title' => ['Judul', 'text', 'required|string|max:150'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:150'], 'body' => ['Isi halaman (teks cadangan)', 'textarea', 'required|string|max:50000'], 'template' => ['Template halaman', 'select', 'sometimes|in:standard,wide,landing', ['standard' => 'Standar', 'wide' => 'Lebar', 'landing' => 'Landing page']], 'blocks' => ['Blok konten', 'blocks', 'nullable|string|max:50000'], 'seo_title' => ['Judul SEO', 'text', 'nullable|string|max:200'], 'seo_description' => ['Deskripsi SEO', 'textarea', 'nullable|string|max:300'], 'editorial_status' => ['Status editorial', 'select', 'required|in:draft,review,approved', ['draft' => 'Draft', 'review' => 'Menunggu review', 'approved' => 'Disetujui']], 'review_note' => ['Catatan editor/reviewer', 'textarea', 'nullable|string|max:1000'], 'starts_at' => ['Mulai tayang', 'datetime-local', 'nullable|date'], 'ends_at' => ['Selesai tayang', 'datetime-local', 'nullable|date|after:starts_at'], 'active' => ['Tayangkan setelah disetujui', 'checkbox', 'boolean']]],
    'newsletter' => ['model' => NewsletterSubscriber::class, 'title' => 'Pelanggan newsletter', 'permission' => 'content.write', 'columns' => ['email' => 'Email', 'name' => 'Nama', 'source' => 'Sumber', 'subscribed_at' => 'Terdaftar', 'active' => 'Aktif'], 'fields' => [
        'email' => ['Email', 'email', 'required|email|max:150'], 'name' => ['Nama (opsional)', 'text', 'nullable|string|max:100'], 'source' => ['Sumber', 'text', 'required|string|max:80'], 'subscribed_at' => ['Tanggal berlangganan', 'datetime-local', 'required|date'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'store-locations' => ['model' => StoreLocation::class, 'title' => 'Lokasi toko', 'permission' => 'content.write', 'columns' => ['name' => 'Lokasi', 'city' => 'Kota', 'hours' => 'Jam buka', 'sort_order' => 'Urutan', 'active' => 'Aktif'], 'fields' => [
        'name' => ['Nama toko / mal', 'text', 'required|string|max:150'], 'address' => ['Alamat', 'textarea', 'required|string|max:1000'], 'city' => ['Kota', 'text', 'nullable|string|max:100'], 'map_url' => ['Tautan Google Maps', 'url', 'nullable|url|max:500'], 'hours' => ['Jam buka', 'text', 'nullable|string|max:150'], 'sort_order' => ['Urutan', 'number', 'required|integer|min:0|max:10000'], 'active' => ['Tampilkan di footer', 'checkbox', 'boolean']]],
];
