@php($chart=collect($series->size_chart))
@if($chart->get('columns') && $chart->get('rows'))
<div class="size-chart-wrap">
    <table class="size-chart"><thead><tr>@foreach($chart->get('columns') as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>@foreach($chart->get('rows') as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table>
    @if($chart->get('notes'))<p class="size-chart-note">{{ $chart->get('notes') }}</p>@endif
</div>
@else
<p class="preserve-lines">{{ $series->size_guide ?: 'Panduan ukuran sedang dilengkapi. Hubungi tim Dthree untuk rekomendasi ukuran.' }}</p>
@endif
