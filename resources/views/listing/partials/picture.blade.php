{{-- Responsive image: WebP and JPEG variants when they exist, otherwise the original. --}}
@php
	$webp = $image->srcset('webp');
	$jpg = $image->srcset('jpg');
	$src = $image->variantUrl('md', 'jpg') ?? $image->variantUrl('md', 'webp') ?? $image->originalUrl();
	$width = (int) ($image->width ?: 800);
	$height = (int) ($image->height ?: 600);
@endphp
<picture>
	@if ($webp !== '')
		<source type="image/webp" srcset="{{ $webp }}" sizes="{{ $sizes }}">
	@endif
	<img src="{{ $src }}" @if ($jpg !== '') srcset="{{ $jpg }}" sizes="{{ $sizes }}" @endif alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}"
		@if ($eager ?? false) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async" class="{{ $class ?? '' }}" @if (! empty($placeholder)) style="background: url('{{ $placeholder }}') center / contain no-repeat" @endif>
</picture>
