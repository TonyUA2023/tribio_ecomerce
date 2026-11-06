@props([
    'title',
    'days',                       // date (Y-m-d, Lima) => ['count' => int, 'total' => float]
    'total' => 0,
    'count' => 0,
    'noun' => ['venta', 'ventas'],
    'color' => '#2176d2',
    'href' => null,
    'cta' => null,
    'empty' => 'Sin movimiento en este período.',
])
@php
    use Illuminate\Support\Carbon;
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $compact = fn ($value) => $value >= 10000 ? number_format($value / 1000, 0) . 'K' : ($value >= 1000 ? number_format($value / 1000, 1) . 'K' : number_format($value, 0));
    $n = count($days);
    $peak = max(array_column($days, 'total') ?: [0]);
    // Round the top of the scale to a clean number (…10, 20, 50, 100…) so the gridlines read.
    $top = 1;
    if ($peak > 0) {
        $magnitude = 10 ** floor(log10($peak));
        $top = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($candidate) => $candidate >= $peak) ?? $peak;
    }
    $step = $n <= 8 ? 1 : (int) ceil($n / 8);
    $noun = (array) $noun;
    $label = fn ($d) => Carbon::parse($d)->locale('es')->isoFormat('ddd D MMM');
    $peakKey = $peak > 0 ? array_search($peak, array_column($days, 'total'), true) : false;
    $keys = array_keys($days);
    $uid = 'bc-' . \Illuminate\Support\Str::random(6);
@endphp
<section class="bc" style="--bc-color: {{ $color }}" x-data="{ table: false }" aria-labelledby="{{ $uid }}-t">
    <header class="bc-head">
        <div>
            <h2 id="{{ $uid }}-t">{{ $title }}</h2>
            <p class="bc-total"><strong>{{ $money($total) }}</strong> <span>· {{ $count }} {{ $count === 1 ? $noun[0] : $noun[1] }}</span></p>
        </div>
        <div class="bc-tools">
            @if($count > 0)<button type="button" class="bc-link" @click="table = !table" :aria-pressed="table" x-text="table ? 'Ver gráfico' : 'Ver tabla'">Ver tabla</button>@endif
            @if($href)<a href="{{ $href }}" class="bc-link">{{ $cta ?? 'Ver todo' }}</a>@endif
        </div>
    </header>

    @if($count === 0)
        <p class="bc-empty">{{ $empty }}</p>
    @else
        <div x-show="!table" class="bc-plot" role="group" aria-label="{{ $title }}: gráfico de columnas por día; usa Ver tabla para leer los valores">
            <div class="bc-lines" aria-hidden="true">
                @foreach([1, .5, 0] as $fraction)
                    <span style="bottom: {{ $fraction * 100 }}%"><em>{{ $compact($top * $fraction) }}</em></span>
                @endforeach
            </div>
            <div class="bc-cols" style="--n: {{ $n }}">
                @foreach($days as $date => $day)
                    @php $i = $loop->index; $pct = $day['total'] > 0 ? max(3, round($day['total'] * 100 / $top)) : 0; @endphp
                    <button type="button" style="--h: {{ $pct }}%" class="bc-col {{ $loop->last ? 'is-last' : '' }} {{ $i < 2 ? 'tip-start' : ($i > $n - 3 ? 'tip-end' : '') }}" aria-label="{{ $label($date) }}: {{ $money($day['total']) }}, {{ $day['count'] }} {{ $day['count'] === 1 ? $noun[0] : $noun[1] }}">
                        @if($peakKey !== false && $keys[$i] === $keys[$peakKey] && $day['total'] > 0)<b class="bc-peak" style="bottom: {{ $pct }}%" aria-hidden="true">{{ $compact($day['total']) }}</b>@endif
                        <i style="height: {{ $pct }}%"></i>
                        <span class="bc-x" aria-hidden="true">{{ ($i % $step === 0 || $loop->last) ? Carbon::parse($date)->locale('es')->isoFormat($n <= 8 ? 'dd D' : 'D') : '' }}</span>
                        <span class="bc-tip" role="tooltip"><strong>{{ $money($day['total']) }}</strong><small>{{ $label($date) }} · {{ $day['count'] }} {{ $day['count'] === 1 ? $noun[0] : $noun[1] }}</small></span>
                    </button>
                @endforeach
            </div>
        </div>
        <div x-show="table" x-cloak class="bc-table">
            <table>
                <thead><tr><th scope="col">Día</th><th scope="col" class="r">{{ ucfirst($noun[1]) }}</th><th scope="col" class="r">Importe</th></tr></thead>
                <tbody>
                    @foreach(array_reverse($days, true) as $date => $day)
                        <tr><th scope="row">{{ $label($date) }}</th><td class="r">{{ $day['count'] }}</td><td class="r">{{ $money($day['total']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
