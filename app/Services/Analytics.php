<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Setting;
use Illuminate\Http\Request;

class Analytics
{
    public function record(Request $request, string $event, array $parameters = []): void
    {
        if (! Setting::get('analytics_enabled', true)) {
            return;
        }
        $allowed = ['view_home', 'view_category', 'search', 'view_item', 'add_to_cart', 'begin_checkout', 'shipping_selected', 'payment_selected', 'purchase'];
        if (! in_array($event, $allowed, true)) {
            return;
        }
        $parameters = array_intersect_key($parameters, array_flip(['category', 'series_id', 'variant_id', 'sku', 'quantity', 'value', 'items', 'results', 'courier', 'service', 'method', 'source', 'campaign']));
        $parameters['device'] = preg_match('/Mobile|Android|iPhone/i', $request->userAgent() ?? '') ? 'mobile' : 'desktop';
        $parameters['source'] ??= mb_substr((string) $request->query('utm_source', 'direct'), 0, 80);
        if ($request->filled('utm_campaign')) {
            $parameters['campaign'] = mb_substr($request->string('utm_campaign')->toString(), 0, 120);
        }
        AnalyticsEvent::create([
            'user_id' => $request->user()?->id,
            'visitor_hash' => hash_hmac('sha256', $request->session()->getId(), config('app.key')),
            'event' => $event,
            'parameters' => $parameters,
            'created_at' => now(),
        ]);
    }
}
