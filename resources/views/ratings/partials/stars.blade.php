{{-- Stars for $value (0-5, fractions shown as a partly filled star). $size in px. --}}
@php
	$size ??= 16;
	$pct = max(0, min(100, round(($value ?? 0) / 5 * 100, 1)));
	$star = '<svg viewBox="0 0 24 24" width="'.$size.'" height="'.$size.'" aria-hidden="true" focusable="false"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"/></svg>';
@endphp
<span class="lgr-stars" role="img" aria-label="Ocena {{ \App\Support\Ratings\RatingPresenter::average((float) $value) }} od 5">
	<span class="lgr-stars__base">{!! str_repeat($star, 5) !!}</span>
	<span class="lgr-stars__fill" style="width: {{ $pct }}%">{!! str_repeat($star, 5) !!}</span>
</span>
