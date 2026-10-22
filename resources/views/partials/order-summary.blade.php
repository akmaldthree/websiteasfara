<div class="table-responsive"><table class="table"><thead><tr><th>Produk / SKU</th><th>Jumlah</th><th>Total</th></tr></thead><tbody>
@foreach($order->items as $i)<tr><td>{{ $i->name }}<br><small>{{ $i->sku }} · {{ implode(' · ',array_filter(collect($i->attributes)->except('custom')->all())) }}<br>{{ $i->fulfillment }} {{ $i->lead_time }}
@if($i->components->isNotEmpty())<br>Isi bundle: @foreach($i->components as $component){{ $component->sku }} × {{ $component->quantity }}{{ !$loop->last?' · ':'' }}@endforeach @endif
@if($i->production_status)<br>Produksi: {{ str_replace('_',' ',$i->production_status) }}@endif
@if(data_get($i->attributes,'custom.measurements'))<br>Ukuran: @foreach(data_get($i->attributes,'custom.measurements') as $key=>$value){{ $key }} {{ $value }}{{ !$loop->last?' · ':'' }}@endforeach @endif
</small></td><td>{{ $i->quantity }}</td><td>@rupiah($i->total)</td></tr>@endforeach
</tbody></table></div>
@foreach(['subtotal'=>'Subtotal produk','custom_cost'=>'Biaya custom','discount'=>'Diskon','shipping_cost'=>'Ongkir','shipping_discount'=>'Subsidi ongkir','cod_fee'=>'Biaya COD','total'=>'Total pembayaran'] as $key=>$label)<div class="summary-row {{ $key==='total'?'total':'' }}"><span>{{ $label }}</span><span>{{ in_array($key,['discount','shipping_discount'])?'− ':'' }}@rupiah($order->$key)</span></div>@endforeach
