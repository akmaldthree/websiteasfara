<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Services\BulkImportService;
use App\Support\SimpleXlsx;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public const COLUMNS = ['series_slug', 'sku', 'product_type', 'color', 'size', 'price', 'stock', 'weight', 'fulfillment', 'lead_time'];

    public function __construct(private BulkImportService $imports, private SimpleXlsx $xlsx) {}

    public function index(Request $request)
    {
        $definitions = collect($this->imports->definitions())->filter(fn (array $definition) => $request->user()->allowed($definition['permission']))->all();

        return view('admin.import', $this->viewData($request->query('type', array_key_first($definitions) ?: 'variants'), $definitions));
    }

    public function template(Request $request)
    {
        [$type, $definition] = $this->selected($request);
        $path = tempnam(sys_get_temp_dir(), 'dthree-template-');
        $this->xlsx->create($path, $definition['label'], $definition['columns']);

        return response()->download($path, 'template-'.$type.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    public function export(Request $request)
    {
        [$type, $definition] = $this->selected($request);
        $path = tempnam(sys_get_temp_dir(), 'dthree-export-');
        $this->xlsx->create($path, $definition['label'], $definition['columns'], $this->imports->exportRows($type));

        return response()->download($path, 'data-'.$type.'-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    public function preview(Request $request)
    {
        [$type, $definition] = $this->selected($request);
        $request->validate(['file' => 'nullable|file|max:10240', 'csv' => 'nullable|file|max:10240', 'mode' => 'nullable|in:create,upsert']);
        $file = $request->file('file') ?: $request->file('csv');
        if (! $file) {
            throw ValidationException::withMessages(['file' => 'Pilih file CSV atau XLSX.']);
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Format file harus CSV atau XLSX.']);
        }
        $rows = $extension === 'xlsx'
            ? $this->xlsx->read($file->getRealPath(), $definition['columns'], BulkImportService::MAXIMUM_ROWS)
            : $this->csv($file->getRealPath(), $definition['columns'], $type === 'variants' ? self::COLUMNS : null);
        $rows = $this->defaults($type, $rows);
        $mode = $request->input('mode', 'create');
        [, $errors] = $this->imports->validateRows($type, $rows, $mode);
        $request->session()->put('import_preview', compact('type', 'rows', 'mode'));

        return view('admin.import', $this->viewData($type) + ['preview' => $rows, 'rowErrors' => $errors, 'mode' => $mode]);
    }

    public function generate(Request $request)
    {
        $this->authorizeType($request, 'variants');
        $data = $request->validate(['series_id' => 'required|exists:series,id', 'sku_prefix' => 'required|regex:/^[A-Za-z0-9._-]+$/|max:40', 'product_types' => 'nullable|string|max:1000', 'colors' => 'required|string|max:2000', 'sizes' => 'required|string|max:1000', 'price' => 'required|integer|between:1,100000000', 'stock' => 'required|integer|between:0,1000000', 'weight' => 'required|integer|between:1,100000', 'fulfillment' => 'required|in:ready_stock,pre_order', 'lead_time' => 'nullable|string|max:150', 'mode' => 'required|in:create,upsert']);
        $series = Series::findOrFail($data['series_id']);
        $split = fn (?string $value) => collect(preg_split('/[,\r\n]+/', $value ?? ''))->map(fn ($item) => trim($item))->filter()->unique()->values()->all();
        $types = $series->uses_product_type ? $split($data['product_types'] ?? '') : [''];
        $colors = $split($data['colors']);
        $sizes = $split($data['sizes']);
        if (! $types || ! $colors || ! $sizes || count($types) * count($colors) * count($sizes) > BulkImportService::MAXIMUM_ROWS) {
            throw ValidationException::withMessages(['colors' => 'Kombinasi harus lengkap dan tidak melebihi batas import.']);
        }
        $rows = [];
        foreach ($types as $productType) {
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $suffix = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', implode('-', array_filter([$productType, $color, $size]))), '-'));
                    $rows[] = ['series_slug' => $series->slug, 'sku' => strtoupper($data['sku_prefix'].'-'.$suffix), 'product_type' => $productType, 'color' => $color, 'size' => $size, 'price' => $data['price'], 'stock' => $data['stock'], 'low_stock' => 5, 'weight' => $data['weight'], 'fulfillment' => $data['fulfillment'], 'measurement_template_name' => '', 'lead_time' => $data['lead_time'] ?? '', 'active' => 1];
                }
            }
        }
        $type = 'variants';
        $mode = $data['mode'];
        [, $errors] = $this->imports->validateRows($type, $rows, $mode);
        $request->session()->put('import_preview', compact('type', 'rows', 'mode'));

        return view('admin.import', $this->viewData($type) + ['preview' => $rows, 'rowErrors' => $errors, 'mode' => $mode]);
    }

    public function commit(Request $request)
    {
        $preview = $request->session()->get('import_preview');
        if (! is_array($preview) || ! isset($preview['type'], $preview['rows'], $preview['mode'])) {
            throw ValidationException::withMessages(['file' => 'Pratinjau import tidak ditemukan.']);
        }
        $this->authorizeType($request, $preview['type']);
        $count = $this->imports->commit($preview['type'], $preview['rows'], $preview['mode'], $request->user()->id);
        $request->session()->forget('import_preview');

        return redirect()->route('admin.import', ['type' => $preview['type']])->with('success', "Import berhasil. {$count} baris disimpan secara atomik.");
    }

    /** @return array<int, array<string, string>> */
    private function csv(string $path, array $columns, ?array $legacyColumns = null): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }
        if ($header !== $columns && $header !== $legacyColumns) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Header CSV tidak sama dengan template modul yang dipilih.']);
        }
        $fileColumns = $header;
        $rows = [];
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            if (count($rows) >= BulkImportService::MAXIMUM_ROWS) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'Jumlah baris melebihi batas import.']);
            }
            $values = array_slice(array_pad($values, count($fileColumns), ''), 0, count($fileColumns));
            $rows[] = array_combine($fileColumns, $values);
        }
        fclose($handle);

        return $rows;
    }

    private function defaults(string $type, array $rows): array
    {
        if ($type !== 'variants') {
            return $rows;
        }

        return array_map(function (array $row) {
            $row['low_stock'] = ($row['low_stock'] ?? '') === '' ? 5 : $row['low_stock'];
            $row['active'] = ($row['active'] ?? '') === '' ? 1 : $row['active'];
            $row['measurement_template_name'] ??= '';

            return $row;
        }, $rows);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function selected(Request $request): array
    {
        $type = $request->input('type', 'variants');
        $definitions = $this->imports->definitions();
        abort_unless(isset($definitions[$type]), 404);
        $this->authorizeType($request, $type);

        return [$type, $definitions[$type]];
    }

    private function authorizeType(Request $request, string $type): void
    {
        $definition = $this->imports->definitions()[$type] ?? null;
        abort_unless($definition && $request->user()->allowed($definition['permission']), 403);
    }

    private function viewData(string $type, ?array $definitions = null): array
    {
        $definitions ??= $this->imports->definitions();
        if (! isset($definitions[$type])) {
            $type = array_key_first($definitions) ?: 'variants';
        }

        return ['definitions' => $definitions, 'selectedType' => $type, 'definition' => $definitions[$type] ?? null, 'seriesOptions' => Series::orderBy('name')->get()];
    }
}
