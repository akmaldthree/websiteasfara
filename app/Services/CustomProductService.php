<?php

namespace App\Services;

use App\Models\CustomQuote;
use App\Models\MeasurementTemplate;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomProductService
{
    /** @return array<string, float|int|string> */
    public function validateMeasurements(MeasurementTemplate $template, array $input): array
    {
        $rules = [];
        $attributes = [];
        foreach ($template->fields as $field) {
            $key = $field['key'] ?? '';
            if (! preg_match('/^[a-z][a-z0-9_]{1,49}$/', $key)) {
                throw ValidationException::withMessages(['measurements' => 'Template ukuran memiliki field yang tidak valid.']);
            }
            $rule = ($field['required'] ?? true) ? ['required'] : ['nullable'];
            $rule[] = 'numeric';
            if (isset($field['min'])) {
                $rule[] = 'min:'.$field['min'];
            }
            if (isset($field['max'])) {
                $rule[] = 'max:'.$field['max'];
            }
            $rules['measurements.'.$key] = $rule;
            $attributes['measurements.'.$key] = $field['label'] ?? $key;
        }

        $validated = Validator::make(['measurements' => $input], $rules, [], $attributes)->validate();

        return $validated['measurements'];
    }

    /** @return array<string, mixed> */
    public function fixedCustomization(Variant $variant, array $input, ?string $referencePath = null): array
    {
        $template = $this->templateFor($variant);
        $this->check($template->pricing_mode === 'fixed', 'Produk ini memerlukan penawaran harga terlebih dahulu.');

        return $this->snapshot($template, $this->validateMeasurements($template, $input), null) + ['reference_path' => $referencePath];
    }

    public function requestQuote(Variant $variant, User $user, array $input, string $requestKey, ?string $referencePath = null): CustomQuote
    {
        $template = $this->templateFor($variant);
        $this->check($template->pricing_mode === 'quote', 'Produk ini menggunakan tarif custom tetap dan tidak memerlukan penawaran.');
        $measurements = $this->validateMeasurements($template, $input);

        return DB::transaction(function () use ($variant, $user, $requestKey, $template, $measurements, $referencePath) {
            if ($existing = CustomQuote::where('request_key', $requestKey)->first()) {
                $this->check($existing->user_id === $user->id && $existing->variant_id === $variant->id, 'Permintaan penawaran tidak valid.');

                return $existing;
            }
            $quote = CustomQuote::create([
                'request_key' => $requestKey, 'number' => 'CQ-'.strtoupper(Str::random(10)),
                'user_id' => $user->id, 'variant_id' => $variant->id, 'measurement_template_id' => $template->id,
                'template_snapshot' => $this->templateSnapshot($template), 'measurements' => $measurements, 'reference_path' => $referencePath,
            ]);
            Audit::record('custom_quote.submitted', 'custom_quote:'.$quote->id, null, $quote->only('number', 'user_id', 'variant_id', 'measurements'));

            return $quote;
        }, 3);
    }

    public function quote(CustomQuote $quote, array $data): void
    {
        DB::transaction(function () use ($quote, $data) {
            $quote = CustomQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->check(in_array($quote->status, ['submitted', 'quoted'], true), 'Penawaran tidak dapat diperbarui pada status ini.');
            $before = $quote->toArray();
            $quote->update([
                'status' => $data['status'],
                'surcharge' => $data['status'] === 'quoted' ? $data['surcharge'] : null,
                'lead_time' => $data['status'] === 'quoted' ? $data['lead_time'] : null,
                'admin_notes' => $data['admin_notes'], 'quoted_by' => auth()->id(), 'quoted_at' => now(),
                'expires_at' => $data['status'] === 'quoted' ? now()->addDays($data['valid_days']) : null,
            ]);
            Audit::record('custom_quote.'.$data['status'], 'custom_quote:'.$quote->id, $before, $quote->toArray());
        });
    }

    /** @return array<string, mixed> */
    public function accept(CustomQuote $quote, User $user): array
    {
        return DB::transaction(function () use ($quote, $user) {
            $quote = CustomQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->check($quote->user_id === $user->id && $quote->status === 'quoted' && $quote->expires_at?->isFuture(), 'Penawaran tidak tersedia atau sudah kedaluwarsa.');
            $quote->update(['status' => 'accepted', 'accepted_at' => now()]);
            Audit::record('custom_quote.accepted', 'custom_quote:'.$quote->id, ['status' => 'quoted'], ['status' => 'accepted']);

            return $this->snapshotData($quote->template_snapshot, $quote->measurements, $quote->surcharge, $quote->lead_time, $quote->id) + ['reference_path' => $quote->reference_path];
        });
    }

    public function updateProduction(OrderItem $item, string $status, ?string $note): void
    {
        $allowed = ['reviewing', 'approved', 'production', 'quality_check', 'ready_to_ship'];
        $this->check($item->fulfillment === 'custom_made' && in_array($status, $allowed, true), 'Status produksi tidak valid.');
        DB::transaction(function () use ($item, $status, $note) {
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $before = $item->production_status;
            $item->update(['production_status' => $status]);
            $milestone = $item->productionMilestones()->create(['user_id' => auth()->id(), 'status' => $status, 'note' => $note]);
            Audit::record('production.updated', 'order_item:'.$item->id, ['status' => $before], ['status' => $status, 'note' => $note]);
            OrderCommunication::record($item->order, 'production-'.$milestone->id, 'production_'.$status, 'Produksi '.$item->name.' ('.$item->sku.') diperbarui menjadi '.str_replace('_', ' ', $status).($note ? '. '.$note : '.'));
        });
    }

    private function templateFor(Variant $variant): MeasurementTemplate
    {
        $this->check($variant->active && $variant->fulfillment === 'custom_made', 'Varian custom tidak tersedia.');
        $template = $variant->measurementTemplate;
        $this->check($template?->active === true, 'Template ukuran belum tersedia.');

        return $template;
    }

    /** @return array<string, mixed> */
    private function snapshot(MeasurementTemplate $template, array $measurements, ?int $quoteId): array
    {
        return $this->snapshotData($this->templateSnapshot($template), $measurements, $template->surcharge, $template->lead_time, $quoteId);
    }

    /** @return array<string, mixed> */
    private function snapshotData(array $template, array $measurements, int $surcharge, string $leadTime, ?int $quoteId): array
    {
        return ['template' => $template, 'measurements' => $measurements, 'surcharge' => $surcharge, 'lead_time' => $leadTime, 'quote_id' => $quoteId, 'approved_at' => now()->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function templateSnapshot(MeasurementTemplate $template): array
    {
        return $template->only('id', 'name', 'product_type', 'pricing_mode', 'fields', 'policy');
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['custom' => $message]);
        }
    }
}
