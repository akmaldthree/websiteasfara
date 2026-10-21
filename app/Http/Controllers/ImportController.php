<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public const COLUMNS = ['series_slug', 'sku', 'product_type', 'color', 'size', 'price', 'stock', 'weight', 'fulfillment', 'lead_time'];

    public function index()
    {
        return view('admin.import');
    }

    public function template()
    {
        return response(implode(',', self::COLUMNS)."\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="template-sku.csv"']);
    }

    public function preview(Request $r)
    {
        $r->validate(['csv' => 'required|file|max:1024']);
        $f = fopen($r->file('csv')->getRealPath(), 'r');
        $header = fgetcsv($f, 0, ',', '"', '');
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }
        if ($header !== self::COLUMNS) {
            fclose($f);
            throw ValidationException::withMessages(['csv' => 'Header CSV harus sama dengan template. Gunakan UTF-8 dan pemisah koma.']);
        }
        $rows = [];
        while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            } if (count($rows) >= 200) {
                fclose($f);
                throw ValidationException::withMessages(['csv' => 'Maksimal 200 baris per import.']);
            } $rows[] = count($row) === count($header) ? array_combine($header, $row) : ['sku' => '', '_error' => 'Jumlah kolom tidak sesuai.'];
        } fclose($f);
        [$clean,$errors] = $this->validateRows($rows);
        $r->session()->put('import_preview', $rows);

        return view('admin.import', ['preview' => $rows, 'rowErrors' => $errors]);
    }

    private function validateRows(array $rows): array
    {
        $errors = [];
        $clean = [];
        $seen = [];
        $combos = [];
        foreach ($rows as $i => $row) {
            $row = array_map(fn ($v) => trim((string) $v), $row);
            $row['sku'] = strtoupper($row['sku'] ?? '');
            $v = Validator::make($row, ['series_slug' => 'required|exists:series,slug', 'sku' => 'required|regex:/^[A-Za-z0-9._-]+$/|max:80|unique:variants,sku', 'product_type' => 'nullable|string|max:100', 'color' => 'required|string|max:80', 'size' => 'required|string|max:40', 'price' => 'required|integer|between:1,100000000', 'stock' => 'required|integer|between:0,1000000', 'weight' => 'required|integer|between:1,100000', 'fulfillment' => 'required|in:ready_stock,pre_order', 'lead_time' => 'nullable|string|max:150']);
            $messages = $v->errors()->all();
            if (isset($seen[$row['sku']])) {
                $messages[] = 'SKU duplikat di file.';
            } $seen[$row['sku']] = true;
            $series = Series::where('slug', $row['series_slug'] ?? '')->first();
            if ($series) {
                if ($series->uses_product_type !== ! empty($row['product_type'])) {
                    $messages[] = 'Jenis produk tidak sesuai struktur series.';
                }
                $combo = hash('sha256', mb_strtolower(implode('|', [$series->id, $row['product_type'] ?? '', $row['color'] ?? '', $row['size'] ?? ''])));
                if (isset($combos[$combo]) || Variant::where('combination_key', $combo)->exists()) {
                    $messages[] = 'Kombinasi varian duplikat.';
                } $combos[$combo] = true;
            }
            if ($messages) {
                $errors[$i + 2] = $messages;
            } else {
                unset($row['series_slug']);
                $row['series_id'] = $series->id;
                $clean[] = $row;
            }
        }
        if (! $rows) {
            $errors[1] = ['File tidak berisi data.'];
        }

return [$clean, $errors];
    }

    public function commit(Request $r)
    {
        $rows = $r->session()->get('import_preview', []);
        DB::transaction(function () use ($rows) {
            [$clean,$errors] = $this->validateRows($rows);
            if ($errors) {
                throw ValidationException::withMessages(['csv' => 'Data berubah atau ada baris tidak valid. Unggah ulang CSV yang sudah diperbaiki.']);
            }
            foreach ($clean as $row) {
                $v = Variant::create($row);
                StockMovement::create(['variant_id' => $v->id, 'user_id' => auth()->id(), 'stock_delta' => $v->stock, 'reserved_delta' => 0, 'reason' => 'Stok awal import CSV']);
            }
            Audit::record('variants.imported', 'csv', null, ['rows' => count($clean), 'skus' => array_column($clean, 'sku')]);
        });
        $r->session()->forget('import_preview');

        return redirect()->route('admin.resources','variants')->with('success','Import berhasil. Semua baris tersimpan.');
    }
}
