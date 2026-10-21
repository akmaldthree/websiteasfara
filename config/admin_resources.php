<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\Series;
use App\Models\ShippingRate;
use App\Models\Variant;

// field: label, input type, validation, optional select options.
return [
    'categories' => ['model' => Category::class, 'title' => 'Kategori', 'permission' => 'catalog.write', 'columns' => ['name' => 'Kategori', 'slug' => 'Slug', 'active' => 'Aktif'], 'fields' => [
        'name' => ['Nama', 'text', 'required|string|max:100'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:100'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'series' => ['model' => Series::class, 'title' => 'Series', 'permission' => 'catalog.write', 'columns' => ['name' => 'Series', 'slug' => 'Slug', 'active' => 'Aktif'], 'fields' => [
        'category_id' => ['Kategori', 'category', 'required|exists:categories,id'], 'name' => ['Nama series', 'text', 'required|string|max:150'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:150'],
        'description' => ['Deskripsi', 'textarea', 'nullable|string|max:10000'], 'material' => ['Bahan', 'textarea', 'nullable|string|max:2000'], 'care' => ['Perawatan', 'textarea', 'nullable|string|max:2000'],
        'size_guide' => ['Panduan ukuran', 'textarea', 'nullable|string|max:5000'], 'image' => ['Foto produk (JPG / PNG / WebP, maks. 3 MB)', 'file', 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072'],
        'uses_product_type' => ['Gunakan jenis produk (Sarimbit)', 'checkbox', 'boolean'], 'featured' => ['Tampilkan di beranda', 'checkbox', 'boolean'], 'active' => ['Terbitkan', 'checkbox', 'boolean']]],
    'variants' => ['model' => Variant::class, 'title' => 'SKU & varian', 'permission' => 'catalog.write', 'columns' => ['sku' => 'SKU manual', 'product_type' => 'Jenis', 'color' => 'Warna', 'size' => 'Ukuran', 'price' => 'Harga', 'stock' => 'Stok / kuota', 'reserved' => 'Reservasi'], 'fields' => [
        'series_id' => ['Series', 'series', 'required|exists:series,id'], 'sku' => ['SKU manual', 'text', 'required|regex:/^[A-Za-z0-9._-]+$/|max:80'], 'product_type' => ['Jenis produk (kosong untuk Abaya)', 'text', 'nullable|string|max:100'],
        'color' => ['Warna', 'text', 'required|string|max:80'], 'size' => ['Ukuran', 'text', 'required|string|max:40'], 'price' => ['Harga (Rupiah)', 'number', 'required|integer|min:1|max:100000000'],
        'weight' => ['Berat (gram)', 'number', 'required|integer|min:1|max:100000'], 'low_stock' => ['Peringatan stok minimum', 'number', 'required|integer|min:0|max:100000'],
        'fulfillment' => ['Metode pemenuhan', 'select', 'required|in:ready_stock,pre_order', ['ready_stock' => 'Ready stock', 'pre_order' => 'Pre-order']],
        'lead_time' => ['Estimasi pemenuhan', 'text', 'nullable|string|max:150'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'shipping-rates' => ['model' => ShippingRate::class, 'title' => 'Tarif pengiriman', 'permission' => 'settings.write', 'columns' => ['courier' => 'Kurir', 'service' => 'Layanan', 'province' => 'Provinsi', 'city' => 'Kota', 'first_kg' => 'Kg pertama', 'cod' => 'COD'], 'fields' => [
        'courier' => ['Kurir', 'select', 'required|in:POS,JNE', ['POS' => 'POS', 'JNE' => 'JNE']], 'service' => ['Layanan', 'text', 'required|string|max:80'],
        'province' => ['Provinsi (* untuk semua)', 'text', 'required|string|max:100'], 'city' => ['Kota / kabupaten (* untuk semua)', 'text', 'required|string|max:100'],
        'first_kg' => ['Tarif kilogram pertama', 'number', 'required|integer|min:0|max:10000000'], 'extra_kg' => ['Tarif setiap kg berikutnya', 'number', 'required|integer|min:0|max:10000000'],
        'estimate' => ['Estimasi pengiriman', 'text', 'required|string|max:100'], 'cod' => ['Izinkan COD pada wilayah ini', 'checkbox', 'boolean'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'coupons' => ['model' => Coupon::class, 'title' => 'Voucher & promosi', 'permission' => 'promotions.write', 'columns' => ['code' => 'Kode', 'type' => 'Jenis', 'value' => 'Nilai', 'used' => 'Terpakai', 'usage_limit' => 'Kuota', 'active' => 'Aktif'], 'fields' => [
        'code' => ['Kode voucher', 'text', 'required|alpha_dash|max:40'], 'type' => ['Jenis', 'select', 'required|in:fixed,percent,shipping', ['fixed' => 'Potongan Rupiah', 'percent' => 'Persentase', 'shipping' => 'Subsidi ongkir']],
        'value' => ['Nilai potongan / persen', 'number', 'required|integer|min:1|max:100000000'], 'minimum' => ['Minimum belanja', 'number', 'required|integer|min:0|max:100000000'],
        'maximum' => ['Maksimum subsidi (Rupiah)', 'number', 'nullable|integer|min:0|max:100000000'], 'usage_limit' => ['Kuota pemakaian', 'number', 'required|integer|min:1|max:1000000'],
        'starts_at' => ['Mulai (WIB)', 'datetime-local', 'required|date'], 'ends_at' => ['Selesai (WIB)', 'datetime-local', 'required|date|after:starts_at'], 'active' => ['Aktif', 'checkbox', 'boolean']]],
    'pages' => ['model' => Page::class, 'title' => 'Halaman & kebijakan', 'permission' => 'content.write', 'columns' => ['title' => 'Judul', 'slug' => 'Slug', 'active' => 'Terbit'], 'fields' => [
        'title' => ['Judul', 'text', 'required|string|max:150'], 'slug' => ['Slug', 'text', 'required|alpha_dash|max:150'], 'body' => ['Isi halaman (teks)', 'textarea', 'required|string|max:50000'], 'active' => ['Terbitkan', 'checkbox', 'boolean']]],
];
