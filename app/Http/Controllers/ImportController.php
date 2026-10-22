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
use ZipArchive;

class ImportController extends Controller
{
    public const COLUMNS = ['series_slug', 'sku', 'product_type', 'color', 'size', 'price', 'stock', 'weight', 'fulfillment', 'lead_time'];

    public function index()
    {
        return view('admin.import', ['seriesOptions' => Series::orderBy('name')->get()]);
    }

    public function template()
    {
        return response(implode(',', self::COLUMNS)."\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="template-sku.csv"']);
    }

    public function preview(Request $r)
    {
        $r->validate(['csv' => 'required|file|max:5120', 'mode' => 'nullable|in:create,upsert']);
        $mode = $r->input('mode', 'create');
        $rows = strtolower($r->file('csv')->getClientOriginalExtension()) === 'xlsx' ? $this->xlsx($r->file('csv')->getRealPath()) : $this->csv($r->file('csv')->getRealPath());
        [, $errors] = $this->validateRows($rows, $mode);
        $r->session()->put('import_preview', compact('rows', 'mode'));

        return view('admin.import', ['preview' => $rows, 'rowErrors' => $errors, 'mode' => $mode, 'seriesOptions' => Series::orderBy('name')->get()]);
    }

    public function generate(Request $r)
    {
        $d = $r->validate(['series_id' => 'required|exists:series,id', 'sku_prefix' => 'required|regex:/^[A-Za-z0-9._-]+$/|max:40', 'product_types' => 'nullable|string|max:1000', 'colors' => 'required|string|max:2000', 'sizes' => 'required|string|max:1000', 'price' => 'required|integer|between:1,100000000', 'stock' => 'required|integer|between:0,1000000', 'weight' => 'required|integer|between:1,100000', 'fulfillment' => 'required|in:ready_stock,pre_order', 'lead_time' => 'nullable|string|max:150', 'mode' => 'required|in:create,upsert']);
        $series = Series::findOrFail($d['series_id']);
        $split = fn ($v) => collect(preg_split('/[,\r\n]+/', $v ?? ''))->map(fn ($x) => trim($x))->filter()->unique()->values()->all();
        $types = $series->uses_product_type ? $split($d['product_types'] ?? '') : [''];
        $colors = $split($d['colors']);
        $sizes = $split($d['sizes']);
        if (! $types || ! $colors || ! $sizes || count($types) * count($colors) * count($sizes) > 200) {
            throw ValidationException::withMessages(['colors' => 'Kombinasi harus lengkap dan maksimal 200 SKU.']);
        }
        $rows = [];
        foreach ($types as $type) {
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $suffix = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', implode('-', array_filter([$type, $color, $size]))), '-'));
                    $rows[] = ['series_slug' => $series->slug, 'sku' => strtoupper($d['sku_prefix'].'-'.$suffix), 'product_type' => $type, 'color' => $color, 'size' => $size, 'price' => $d['price'], 'stock' => $d['stock'], 'weight' => $d['weight'], 'fulfillment' => $d['fulfillment'], 'lead_time' => $d['lead_time'] ?? ''];
                }
            }
        }
        $mode = $d['mode'];
        [, $errors] = $this->validateRows($rows, $mode);
        $r->session()->put('import_preview', compact('rows', 'mode'));

        return view('admin.import', ['preview' => $rows, 'rowErrors' => $errors, 'mode' => $mode, 'seriesOptions' => Series::orderBy('name')->get()]);
    }

    private function csv(string $path): array
    {
        $f = fopen($path, 'r');
        $header = fgetcsv($f, 0, ',', '"', '');
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }
        if ($header !== self::COLUMNS) {
            fclose($f);
            throw ValidationException::withMessages(['csv' => 'Header harus sama dengan template Dthree.']);
        }
        $rows = [];
        while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }if (count($rows) >= 200) {
                throw ValidationException::withMessages(['csv' => 'Maksimal 200 baris.']);
            }$rows[] = count($row) === count($header) ? array_combine($header, $row) : ['_error' => 'Jumlah kolom tidak sesuai.'];
        } fclose($f);

        return $rows;
    }

    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || ! ($sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml'))) {
            throw ValidationException::withMessages(['csv' => 'XLSX tidak dapat dibaca. Gunakan worksheet pertama.']);
        }
        $shared = [];
        if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
            foreach (simplexml_load_string($xml)->si as $item) {
                $shared[] = implode('', array_map(fn ($n) => (string) $n, $item->xpath('.//t')));
            }
        }
        $matrix = [];
        foreach (simplexml_load_string($sheetXml)->sheetData->row as $row) {
            $values = array_fill(0, count(self::COLUMNS), '');
            foreach ($row->c as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $m);
                $col = 0;
                foreach (str_split($m[0] ?? '') as $letter) {
                    $col = $col * 26 + ord($letter) - 64;
                }if ($col < 1 || $col > count(self::COLUMNS)) {
                    continue;
                }$value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = implode('', array_map(fn ($n) => (string) $n, $cell->is->xpath('.//t')));
                }$values[$col - 1] = $value;
            }$matrix[] = $values;
        } $zip->close();
        if (array_shift($matrix) !== self::COLUMNS) {
            throw ValidationException::withMessages(['csv' => 'Header XLSX harus sama dengan template Dthree.']);
        }
        if (count($matrix) > 200) {
            throw ValidationException::withMessages(['csv' => 'Maksimal 200 baris.']);
        }

        return array_map(fn ($row) => array_combine(self::COLUMNS, $row), $matrix);
    }

    private function validateRows(array $rows, string $mode): array
    {
        $errors = [];
        $clean = [];
        $seen = [];
        $combos = [];
        foreach ($rows as $i => $row) {
            $row = array_map(fn ($v) => trim((string) $v), $row);
            $row['sku'] = strtoupper($row['sku'] ?? '');
            $existing = Variant::where('sku', $row['sku'])->first();
            $messages = Validator::make($row, ['series_slug' => 'required|exists:series,slug', 'sku' => 'required|regex:/^[A-Za-z0-9._-]+$/|max:80', 'product_type' => 'nullable|string|max:100', 'color' => 'required|string|max:80', 'size' => 'required|string|max:40', 'price' => 'required|integer|between:1,100000000', 'stock' => 'required|integer|between:0,1000000', 'weight' => 'required|integer|between:1,100000', 'fulfillment' => 'required|in:ready_stock,pre_order', 'lead_time' => 'nullable|string|max:150'])->errors()->all();
            if ($mode === 'create' && $existing) {
                $messages[] = 'SKU sudah ada.';
            }if (isset($seen[$row['sku']])) {
                $messages[] = 'SKU duplikat di file.';
            }$seen[$row['sku']] = true;
            $series = Series::where('slug', $row['series_slug'] ?? '')->first();
            if ($series) {
                if ($series->uses_product_type !== ! empty($row['product_type'])) {
                    $messages[] = 'Jenis produk tidak sesuai struktur series.';
                }$combo = hash('sha256', mb_strtolower(implode('|', [$series->id, $row['product_type'] ?? '', $row['color'] ?? '', $row['size'] ?? ''])));
                if (isset($combos[$combo]) || Variant::where('combination_key', $combo)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists()) {
                    $messages[] = 'Kombinasi varian duplikat.';
                }$combos[$combo] = true;
            }
            if ($messages) {
                $errors[$i + 2] = $messages;
            } else {
                unset($row['series_slug']);
                $row['series_id'] = $series->id;
                $clean[] = $row;
            }
        } if (! $rows) {
            $errors[1] = ['File tidak berisi data.'];
        }

        return [$clean, $errors];
    }

    public function commit(Request $r)
    {
        $p = $r->session()->get('import_preview');
        if (! is_array($p) || ! isset($p['rows'],$p['mode'])) {
            throw ValidationException::withMessages(['csv' => 'Pratinjau import tidak ditemukan.']);
        }
        DB::transaction(function () use ($p) {
            [$clean,$errors] = $this->validateRows($p['rows'], $p['mode']);
            if ($errors) {
                throw ValidationException::withMessages(['csv' => 'Data berubah. Buat pratinjau ulang.']);
            }foreach ($clean as $row) {
                $v = Variant::where('sku', $row['sku'])->lockForUpdate()->first();
                $old = $v?->stock ?? 0;
                if ($v) {
                    $v->update($row);
                } else {
                    $v = Variant::create($row);
                }$delta = $v->stock - $old;
                if ($delta) {
                    StockMovement::create(['variant_id' => $v->id, 'user_id' => auth()->id(), 'stock_delta' => $delta, 'reserved_delta' => 0, 'reason' => $old ? 'Penyesuaian stok import upsert' : 'Stok awal import']);
                }
            }Audit::record('variants.imported', $p['mode'], null, ['rows' => count($clean), 'skus' => array_column($clean, 'sku')]);
        });
        $r->session()->forget('import_preview');

        return redirect()->route('admin.resources', 'variants')->with('success', 'Import berhasil. Semua baris tersimpan secara atomik.');
    }
}
