{{--
	Daily bar chart as inline SVG (no JS). One series, one hue, bars anchored to the baseline with
	rounded tops, recessive grid, a native tooltip per day (<title>), the per-day table is on the page.
	$id, $label, $unit (plural noun, e.g. "pregleda"), $points: list of ['date' => 'Y-m-d', 'value' => int]
--}}
@php
	$w = 600; $h = 180; $left = 34; $right = 6; $top = 10; $bottom = 24;
	$plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
	$n = max(1, count($points));
	$max = max(1, max(array_column($points, 'value') ?: [0]));
	// Nice ceiling: 1, 2, 5 × 10^k.
	$mag = 10 ** floor(log10($max)); $ceil = $mag;
	foreach ([1, 2, 5, 10] as $m) { if ($m * $mag >= $max) { $ceil = $m * $mag; break; } }
	$step = $plotW / $n; $gap = $n > 60 ? 1 : 2; $barW = max(1, $step - $gap);
	$r = min(4, $barW / 2);
	$y = fn ($v) => $top + $plotH - ($v / $ceil) * $plotH;
	$fmt = fn ($date) => \Carbon\CarbonImmutable::parse($date)->format('j. n.');
	$total = array_sum(array_column($points, 'value'));
	$labelIdx = array_unique([0, intdiv($n - 1, 2), $n - 1]);
@endphp
<figure class="lgf-card lgf-chart">
	<h3 id="{{ $id }}-naslov">{{ $label }}</h3>
	<p class="lgf-chart__sub">Ukupno {{ number_format($total, 0, ',', '.') }}, najviše {{ number_format(max(array_column($points, 'value') ?: [0]), 0, ',', '.') }} u danu</p>
	<svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-labelledby="{{ $id }}-naslov {{ $id }}-opis">
		<desc id="{{ $id }}-opis">{{ $label }} po danu, od {{ $fmt($points[0]['date'] ?? now()) }} do {{ $fmt($points[$n - 1]['date'] ?? now()) }}. Ukupno {{ $total }}.</desc>
		@foreach ([0, $ceil / 2, $ceil] as $tick)
			<line class="{{ $tick == 0 ? 'base' : 'grid' }}" x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ round($y($tick), 1) }}" y2="{{ round($y($tick), 1) }}"/>
			<text x="{{ $left - 6 }}" y="{{ round($y($tick), 1) + 4 }}" text-anchor="end">{{ $tick == (int) $tick ? (int) $tick : number_format($tick, 1, ',', '') }}</text>
		@endforeach
		@foreach ($points as $i => $point)
			@php
				$x = $left + $i * $step + $gap / 2;
				$v = (int) $point['value'];
				$yTop = $y($v);
				$bh = $top + $plotH - $yTop;
			@endphp
			<g class="col">
				<title>{{ $fmt($point['date']) }}: {{ $v }} {{ $unit }}</title>
				@if ($v > 0)
					@php($rr = min($r, $bh))
					<path class="bar" d="M{{ round($x, 2) }},{{ round($top + $plotH, 2) }}V{{ round($yTop + $rr, 2) }}Q{{ round($x, 2) }},{{ round($yTop, 2) }} {{ round($x + $rr, 2) }},{{ round($yTop, 2) }}H{{ round($x + $barW - $rr, 2) }}Q{{ round($x + $barW, 2) }},{{ round($yTop, 2) }} {{ round($x + $barW, 2) }},{{ round($yTop + $rr, 2) }}V{{ round($top + $plotH, 2) }}Z"/>
				@endif
				<rect class="hit" x="{{ round($left + $i * $step, 2) }}" y="{{ $top }}" width="{{ round($step, 2) }}" height="{{ $plotH }}"/>
			</g>
		@endforeach
		@foreach ($labelIdx as $i)
			<text x="{{ round($left + $i * $step + $step / 2, 1) }}" y="{{ $h - 6 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}">{{ $fmt($points[$i]['date']) }}</text>
		@endforeach
	</svg>
</figure>
