<?php

namespace App\Services;

use App\Models\CaseItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Variant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AfterSalesService
{
    public function __construct(private LoyaltyService $loyalty) {}

    public function policy(): array
    {
        return [
            'return_days' => (int) Setting::get('return_days', 7),
            'warranty_days' => (int) Setting::get('warranty_days', 30),
            'evidence_required' => (bool) Setting::get('case_evidence_required', true),
            'reasons' => Setting::get('return_reasons', ['Barang rusak', 'Barang tidak sesuai', 'Ukuran tidak sesuai']),
            'excluded_skus' => Setting::get('return_excluded_skus', []),
            'shipping_policy' => Setting::get('return_shipping_policy', 'Biaya pengiriman dikonfirmasi CS setelah peninjauan.'),
        ];
    }

    public function open(Order $order, array $data, ?string $path): ServiceCase
    {
        return DB::transaction(function () use ($order, $data, $path) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($existing = ServiceCase::where('request_key', $data['request_key'])->first()) {
                $this->check($existing->order_id === $order->id, 'Permintaan tidak valid.');

                return $existing;
            }
            $policy = $this->policy();
            $selected = array_filter($data['items'] ?? [], fn ($quantity) => (int) $quantity > 0);
            if ($data['type'] !== 'complaint') {
                $received = $order->received_at ?? $order->completed_at;
                $days = $data['type'] === 'return' ? $policy['return_days'] : $policy['warranty_days'];
                $this->check($order->fulfillment_status === 'received' && $received &&
                    $received->copy()->addDays($days)->isFuture(), 'Pesanan belum diterima atau melewati batas hari pengajuan. Hubungi CS melalui komplain untuk peninjauan khusus.');
                $this->check(in_array($data['reason_code'] ?? '', $policy['reasons'], true), 'Pilih alasan yang tersedia.');
                $this->check(! $policy['evidence_required'] || $path !== null, 'Lampirkan bukti untuk permintaan retur/garansi.');
                $this->check(count($selected) > 0, 'Pilih item dan jumlah yang diajukan.');
            }
            $items = $order->items()->get()->keyBy('id');
            foreach ($selected as $id => $quantity) {
                $item = $items->get($id);
                $this->check($item !== null, 'Item tidak berasal dari pesanan ini.');
                $this->check($item->fulfillment !== 'custom_made' &&
                    ! in_array($item->sku, $policy['excluded_skus'], true), 'SKU '.$item->sku.' mengikuti kebijakan khusus. Hubungi CS melalui komplain.');
                $requested = CaseItem::where('order_item_id', $id)
                    ->whereHas('serviceCase', fn ($q) => $q->where('status', '!=', 'rejected'))->sum('quantity');
                $this->check((int) $quantity <= $item->quantity - $requested, 'Jumlah pengajuan '.$item->sku.' melebihi sisa item yang dapat diretur.');
            }
            $case = ServiceCase::create([
                'order_id' => $order->id, 'request_key' => $data['request_key'],
                'number' => 'CS-'.strtoupper(Str::random(10)), 'type' => $data['type'], 'reason' => $data['reason'],
                'reason_code' => $data['reason_code'] ?? null, 'evidence_path' => $path, 'policy_snapshot' => $policy,
            ]);
            foreach ($selected as $id => $quantity) {
                $case->items()->create(['order_item_id' => $id, 'quantity' => $quantity]);
            }
            $case->events()->create(['user_id' => auth()->id(), 'type' => 'opened', 'message' => 'Permintaan diterima dan menunggu peninjauan.']);
            Audit::record('case.created', 'case:'.$case->id, null, ['type' => $case->type, 'items' => $selected]);
            OrderCommunication::record($order, 'case-'.$case->id, 'case_opened', 'Permintaan '.$case->number.' telah diterima. Tim Dthree akan meninjau permintaan Anda.');

            return $case;
        }, 3);
    }

    public function update(ServiceCase $case, array $data): void
    {
        DB::transaction(function () use ($case, $data) {
            $order = Order::whereKey($case->order_id)->lockForUpdate()->firstOrFail();
            $case = ServiceCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $allowed = [
                'open' => ['reviewing', 'approved', 'rejected'], 'reviewing' => ['reviewing', 'approved', 'rejected'],
                'approved' => ['approved', 'resolved'], 'resolved' => [], 'rejected' => [],
            ];
            $this->check(in_array($data['status'], $allowed[$case->status] ?? [], true), 'Perubahan status kasus tidak diizinkan.');
            if ($data['status'] === 'resolved' && $case->items()->exists()) {
                $this->check(! $case->items()->whereNull('received_at')->exists(), 'Gudang harus mencatat pemeriksaan seluruh item sebelum kasus diselesaikan.');
            }
            $before = $case->only('status', 'owner_id', 'resolution');
            $case->update(['status' => $data['status'], 'resolution' => $data['resolution'],
                'owner_id' => auth()->id(), 'closed_at' => in_array($data['status'], ['resolved', 'rejected']) ? now() : null]);
            $event = $case->events()->create(['user_id' => auth()->id(), 'type' => $data['status'], 'message' => $data['resolution']]);
            Audit::record('case.updated', 'case:'.$case->id, $before, $case->only('status', 'owner_id', 'resolution'));
            OrderCommunication::record($order, 'case-event-'.$event->id, 'case_updated',
                'Kasus '.$case->number.': '.__('status.'.$case->status).'. '.$data['resolution']);
        }, 3);
    }

    public function receive(CaseItem $caseItem, int $restock, string $reason): void
    {
        DB::transaction(function () use ($caseItem, $restock, $reason) {
            $order = Order::whereKey($caseItem->serviceCase->order_id)->lockForUpdate()->firstOrFail();
            $case = ServiceCase::whereKey($caseItem->service_case_id)->lockForUpdate()->firstOrFail();
            $line = CaseItem::whereKey($caseItem->id)->lockForUpdate()->firstOrFail();
            if ($line->received_at) {
                $this->check($line->restocked_quantity === $restock, 'Item sudah diperiksa; hasil tidak dapat ditimpa.');

                return;
            }
            $this->check($case->status === 'approved' && $order->inventory_state === 'committed', 'Kasus harus disetujui dan stok pesanan sudah dikomit.');
            $this->check($restock >= 0 && $restock <= $line->quantity, 'Jumlah layak jual melebihi jumlah retur.');
            $variant = Variant::whereKey($line->item->variant_id)->lockForUpdate()->firstOrFail();
            if ($restock > 0) {
                $variant->increment('stock', $restock);
                StockMovement::create(['variant_id' => $variant->id, 'order_id' => $order->id, 'user_id' => auth()->id(),
                    'stock_delta' => $restock, 'reserved_delta' => 0, 'reason' => 'Retur '.$case->number.': '.Str::limit($reason, 180)]);
            }
            $line->update(['received_at' => now(), 'restocked_quantity' => $restock]);
            $case->events()->create(['user_id' => auth()->id(), 'type' => 'received',
                'message' => $line->item->sku.': diterima '.$line->quantity.', layak jual '.$restock.'. '.$reason]);
            Audit::record('return.received', 'case_item:'.$line->id, null, ['quantity' => $line->quantity, 'restocked' => $restock, 'reason' => $reason]);
        }, 3);
    }

    public function refund(Order $order, array $data, string $path): Refund
    {
        return DB::transaction(function () use ($order, $data, $path) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($existing = Refund::where('request_key', $data['request_key'])->first()) {
                $this->check($existing->order_id === $order->id, 'Permintaan refund tidak valid.');

                return $existing;
            }
            $this->check($order->paid_at !== null, 'Pembayaran belum diterima; refund tidak dapat dicatat.');
            $this->check(! Refund::where('order_id', $order->id)->where('reference', $data['reference'])->exists(), 'Referensi refund telah dicatat.');
            $already = (int) $order->refunds()->sum('amount');
            $amount = (int) $data['amount'];
            $this->check($amount > 0 && $amount <= $order->total - $already, 'Refund melebihi sisa pembayaran yang diterima.');
            $this->check(Carbon::parse($data['paid_at'])->greaterThanOrEqualTo($order->paid_at->copy()->startOfDay()), 'Tanggal refund mendahului penerimaan pembayaran.');
            $caseId = $data['service_case_id'] ?? null;
            if ($caseId) {
                $case = $order->cases()->whereKey($caseId)->first();
                $this->check($case && in_array($case->status, ['approved', 'resolved']), 'Kasus refund harus milik pesanan ini dan telah disetujui.');
            }
            $beforeShipping = ! in_array($order->fulfillment_status, ['shipped', 'received']);
            if ($beforeShipping) {
                $this->check($amount + $already === (int) $order->total, 'Pembatalan sebelum pengiriman harus mengembalikan seluruh sisa pembayaran.');
                if ($order->inventory_state === 'committed') {
                    $targets = [];
                    foreach ($order->items()->with('components')->get() as $item) {
                        if ($item->components->isNotEmpty()) {
                            foreach ($item->components as $component) {
                                $targets[$component->variant_id] = ($targets[$component->variant_id] ?? 0) + $component->quantity;
                            }
                        } else {
                            $targets[$item->variant_id] = ($targets[$item->variant_id] ?? 0) + $item->quantity;
                        }
                    }
                    ksort($targets);
                    foreach ($targets as $variantId => $quantity) {
                        $variant = Variant::whereKey($variantId)->lockForUpdate()->firstOrFail();
                        $variant->increment('stock', $quantity);
                        StockMovement::create(['variant_id' => $variant->id, 'order_id' => $order->id, 'user_id' => auth()->id(),
                            'stock_delta' => $quantity, 'reserved_delta' => 0, 'reason' => 'Pembatalan berbayar / refund penuh']);
                    }
                    $order->inventory_state = 'released';
                }
                $order->status = 'cancelled';
            }
            $refund = Refund::create([
                'order_id' => $order->id, 'service_case_id' => $caseId, 'user_id' => auth()->id(),
                'request_key' => $data['request_key'], 'amount' => $amount, 'method' => $data['method'],
                'reference' => $data['reference'], 'paid_at' => $data['paid_at'], 'reason' => $data['reason'], 'proof_path' => $path,
            ]);
            $order->payment_status = $already + $amount === (int) $order->total ? 'refunded' : 'partially_refunded';
            $order->save();
            $this->loyalty->adjustRefund($order, $refund, $already + $amount);
            Audit::record('refund.recorded', 'refund:'.$refund->id, null, $refund->only('order_id', 'amount', 'method', 'reference', 'paid_at'));
            OrderCommunication::record($order, 'refund-'.$refund->id, 'refund_recorded',
                'Pengembalian dana sebesar Rp '.number_format($amount, 0, ',', '.').' telah dicatat melalui '.$refund->method.'.');

            return $refund;
        }, 3);
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['service' => $message]);
        }
    }
}
