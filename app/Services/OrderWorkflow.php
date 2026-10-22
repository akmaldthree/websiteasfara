<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflow
{
    public function act(Order $order, string $action, ?string $reason = null, ?string $tracking = null): void
    {
        DB::transaction(function () use ($order, $action, $reason, $tracking) {
            $o = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = $o->only(['status', 'payment_status', 'fulfillment_status', 'inventory_state']);
            $eventKey = $action;
            if (in_array($action, ['approve', 'cod_confirm'])) {
                if ($o->status === 'confirmed') {
                    return;
                }
                $this->check($o->status === 'waiting', 'Pesanan tidak lagi menunggu konfirmasi.');
                if ($action === 'approve') {
                    $this->check($o->payment_method === 'transfer' && $o->payment_status === 'review', 'Tidak ada transfer menunggu verifikasi.');
                    $proof = $o->proofs()->where('status', 'pending')->latest('id')->firstOrFail();
                    $proof->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                    $o->payment_status = 'paid';
                    $o->paid_at = now();
                } else {
                    $this->check($o->payment_method === 'cod' && $o->expires_at->isFuture(), 'COD tidak valid atau sudah kedaluwarsa.');
                }
                $this->inventory($o, 'commit');
                $o->status = 'confirmed';
                $o->fulfillment_status = 'processing';
            } elseif ($action === 'reject') {
                $this->check($o->status === 'waiting' && $o->payment_status === 'review', 'Tidak ada bukti menunggu verifikasi.');
                $this->check((bool) $reason, 'Alasan penolakan wajib diisi.');
                $eventKey = 'reject-'.$o->proofs()->where('status', 'pending')->latest('id')->value('id');
                $o->proofs()->where('status', 'pending')->update(['status' => 'rejected', 'reason' => $reason, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                $o->payment_status = 'rejected';
                $o->rejection_reason = $reason;
                $o->expires_at = now()->addHours((int) Setting::get('payment_hours', 24));
            } elseif (in_array($action, ['cancel', 'expire'])) {
                if ($o->status === 'cancelled') {
                    return;
                }
                if ($action === 'expire') {
                    $this->check($o->status === 'waiting' && $o->expires_at->isPast() && $o->payment_status !== 'review', 'Pesanan ini tidak dapat kedaluwarsa.');
                } else {
                    $this->check((bool) $reason, 'Alasan pembatalan wajib diisi.');
                    $this->check($o->paid_at === null && in_array($o->status, ['waiting', 'confirmed']) && ! in_array($o->fulfillment_status, ['shipped', 'received']), 'Pesanan dibayar atau dikirim memerlukan proses retur/refund.');
                }
                $this->inventory($o, 'release');
                $o->status = 'cancelled';
                if ($action === 'expire') {
                    $o->payment_status = 'expired';
                }
            } elseif ($action === 'pack') {
                $this->check($o->status === 'confirmed' && $o->fulfillment_status === 'processing', 'Pesanan belum siap dikemas.');
                $o->fulfillment_status = 'packed';
            } elseif ($action === 'ship') {
                $this->check($o->status === 'confirmed' && $o->fulfillment_status === 'packed' && (bool) $tracking, 'Pesanan harus dikemas dan nomor resi wajib diisi.');
                $o->tracking_number = $tracking;
                $o->fulfillment_status = 'shipped';
                $o->shipped_at = now();
            } elseif ($action === 'receive') {
                $this->check($o->fulfillment_status === 'shipped', 'Pesanan belum dikirim.');
                $o->fulfillment_status = 'received';
                $o->received_at = now();
                if ($o->paid_at !== null) {
                    $o->status = 'completed';
                    $o->completed_at = now();
                }
            } elseif ($action === 'cod_paid') {
                $this->check($o->payment_method === 'cod' && $o->status === 'confirmed' && in_array($o->fulfillment_status, ['shipped', 'received']), 'COD belum dikirim.');
                if ($o->paid_at !== null) {
                    return;
                }
                $o->payment_status = 'paid';
                $o->paid_at = now();
                if ($o->fulfillment_status === 'received') {
                    $o->status = 'completed';
                    $o->completed_at = now();
                }
            } else {
                $this->check(false, 'Tindakan tidak dikenal.');
            }
            $o->save();
            Audit::record('order.'.$action, 'order:'.$o->id, $before, $o->only(['status', 'payment_status', 'fulfillment_status', 'inventory_state']) + ['reason' => $reason]);
            $message = match ($action) {
                'approve' => 'Pembayaran transfer telah disetujui. Pesanan mulai diproses.',
                'reject' => 'Bukti transfer ditolak: '.$reason.'. Silakan unggah ulang sebelum batas waktu terbaru.',
                'cod_confirm' => 'Pesanan COD telah dikonfirmasi dan mulai diproses.',
                'cancel' => 'Pesanan dibatalkan: '.$reason,
                'expire' => 'Pesanan kedaluwarsa karena melewati batas konfirmasi.',
                'pack' => 'Pesanan selesai dikemas.',
                'ship' => 'Pesanan dikirim melalui '.$o->shipping['courier'].'. Nomor resi: '.$tracking,
                'receive' => 'Paket telah ditandai diterima.',
                'cod_paid' => 'Pembayaran COD telah direkonsiliasi.',
            };
            OrderCommunication::record($o, $eventKey, $action, $message);
        }, 3);
    }

    private function inventory(Order $o, string $action): void
    {
        if ($action === 'commit' && $o->inventory_state !== 'reserved') {
            return;
        }
        if ($action === 'release' && $o->inventory_state === 'released') {
            return;
        }
        foreach ($o->items()->orderBy('variant_id')->get() as $item) {
            $v = Variant::whereKey($item->variant_id)->lockForUpdate()->firstOrFail();
            $stock = 0;
            $reserved = 0;
            if ($action === 'commit') {
                $stock = -$item->quantity;
                $reserved = -$item->quantity;
            } elseif ($o->inventory_state === 'reserved') {
                $reserved = -$item->quantity;
            } elseif ($o->inventory_state === 'committed') {
                $stock = $item->quantity;
            }
            $this->check($v->stock + $stock >= 0 && $v->reserved + $reserved >= 0, 'Saldo inventori tidak konsisten.');
            $v->update(['stock' => $v->stock + $stock, 'reserved' => $v->reserved + $reserved]);
            StockMovement::create(['variant_id' => $v->id, 'order_id' => $o->id, 'user_id' => auth()->id(), 'stock_delta' => $stock, 'reserved_delta' => $reserved, 'reason' => $action === 'commit' ? 'Konfirmasi pesanan' : 'Pelepasan stok pesanan']);
        }
        $o->inventory_state = $action === 'commit' ? 'committed' : 'released';
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['order' => $message]);
        }
    }
}
