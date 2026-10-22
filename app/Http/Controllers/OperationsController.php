<?php

namespace App\Http\Controllers;

use App\Models\CaseItem;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Services\AfterSalesService;
use App\Services\Audit;
use App\Services\DeliveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function cases(Request $request): View
    {
        $query = ServiceCase::with('order')->withCount('items');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.cases', ['cases' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function caseDetail(ServiceCase $case): View
    {
        return view('admin.case', ['case' => $case->load('order.refunds', 'items.item', 'events')]);
    }

    public function caseEvidence(ServiceCase $case): StreamedResponse
    {
        abort_unless(auth()->user()->allowed('cases.write') || auth()->user()->allowed('payments.verify'), 403);
        abort_unless($case->evidence_path, 404);

        return Storage::disk('local')->download($case->evidence_path, 'bukti-'.$case->number.'.'.pathinfo($case->evidence_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store']);
    }

    public function receiveReturn(Request $request, CaseItem $caseItem, AfterSalesService $service): RedirectResponse
    {
        $data = $request->validate(['restock' => 'required|integer|min:0|max:100', 'reason' => 'required|string|min:5|max:500']);
        $service->receive($caseItem, (int) $data['restock'], $data['reason']);

        return back()->with('success', 'Pemeriksaan retur dan stok dicatat.');
    }

    public function refund(Request $request, Order $order, AfterSalesService $service): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => 'required|uuid', 'amount' => 'required|integer|min:1',
            'method' => 'required|string|max:80', 'reference' => 'required|string|max:100',
            'reason' => 'required|string|min:5|max:2000', 'paid_at' => 'required|date|before_or_equal:now',
            'service_case_id' => 'nullable|integer', 'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        $path = $request->file('proof')->store('refund-proofs', 'local');
        try {
            $refund = $service->refund($order, $data, $path);
            if ($refund->proof_path !== $path) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Pengembalian dana dicatat. Tidak ada transfer bank otomatis.');
    }

    public function refundProof(Refund $refund): StreamedResponse
    {
        return Storage::disk('local')->download($refund->proof_path, 'refund-'.$refund->id.'.'.pathinfo($refund->proof_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, no-store']);
    }

    public function notifications(Request $request): View
    {
        $query = NotificationDelivery::with('event.order');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.notifications', ['deliveries' => $query->latest()->paginate(20)->withQueryString(),
            'counts' => NotificationDelivery::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function notificationRetry(Request $request, NotificationDelivery $delivery, DeliveryService $service): RedirectResponse
    {
        $request->validate(['confirmed' => 'accepted']);
        $service->retry($delivery, $request->boolean('confirmed'));

        return back()->with('success', 'Notifikasi dijadwalkan ulang.');
    }

    public function notificationManual(Request $request, NotificationDelivery $delivery): RedirectResponse
    {
        $request->validate(['note' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($delivery, $request) {
            $row = NotificationDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status, ['manual', 'failed', 'uncertain']), 422);
            $row->update(['status' => 'manual_sent', 'sent_at' => now(), 'claimed_at' => null]);
            Audit::record('notification.manual', 'notification:'.$row->id, null, ['note' => $request->input('note')]);
        });

        return back()->with('success', 'Tindak lanjut manual dicatat.');
    }

    public function policy(AfterSalesService $service): View
    {
        return view('admin.service-policy', ['policy' => $service->policy()]);
    }

    public function savePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_days' => 'required|integer|between:1,365', 'warranty_days' => 'required|integer|between:1,1095',
            'return_reasons' => 'required|string|max:2000', 'return_excluded_skus' => 'nullable|string|max:10000',
            'return_shipping_policy' => 'required|string|max:1000',
        ]);
        foreach (['return_reasons', 'return_excluded_skus'] as $key) {
            $data[$key] = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $data[$key] ?? '')))));
        }
        $data['case_evidence_required'] = $request->boolean('case_evidence_required');
        $data['return_excluded_skus'] = array_map('strtoupper', $data['return_excluded_skus']);
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                Audit::record('setting.updated', $key, Setting::get($key), $value);
                Setting::put($key, $value);
            }
        });

        return back()->with('success', 'Aturan pengajuan baru diperbarui. Snapshot kasus lama dipertahankan.');
    }

    public function invoice(Order $order): View
    {
        return view('store.document', ['order' => $order->load('items.components', 'refunds'), 'kind' => 'invoice']);
    }

    public function document(Request $request, Order $order, string $kind): View
    {
        abort_unless(in_array($kind, ['invoice', 'picking', 'packing', 'label']), 404);
        if ($kind !== 'invoice') {
            abort_unless($request->user()->allowed('shipping.write'), 403);
        }

        return view('store.document', ['order' => $order->load('items.components', 'refunds'), 'kind' => $kind]);
    }
}
