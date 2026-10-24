{{-- What the buyer gets, by product type (config('payments.catalog')). --}}
@use('App\Services\Payments\ProductCatalog')
@php($days = ProductCatalog::days($product))
@switch(ProductCatalog::type($product))
	@case(ProductCatalog::BUMP)
		Oglas se vraća na vrh liste u svojoj kategoriji, kao da je upravo postavljen. Oglas može da se podigne jednom u {{ (int) config('payments.bump_cooldown_hours') }} {{ \App\Support\Listing\Text::plural((int) config('payments.bump_cooldown_hours'), 'sat', 'sata', 'sati') }}.
		@break
	@case(ProductCatalog::BUMP_PACK)
		{{ ProductCatalog::definition($product)['bumps'] ?? 10 }} podizanja koja koristite kad vam odgovara, za bilo koji svoj aktivan oglas.
		@break
	@case(ProductCatalog::PROMOTION)
		{{ trim((string) $product->description) !== '' ? $product->description : 'Oglas je promovisan.' }} Traje {{ $days }} {{ \App\Support\Listing\Text::plural($days, 'dan', 'dana', 'dana') }} od uplate; ako je usluga već uključena, produžava se.
		@break
	@case(ProductCatalog::SLOTS)
		Još {{ \App\Support\Listing\Text::activeListings(ProductCatalog::slots($product)) }}, pored besplatnih, tokom {{ $days }} {{ \App\Support\Listing\Text::plural($days, 'dan', 'dana', 'dana') }} od uplate.
		@break
	@case(ProductCatalog::FIRM_PACKAGE)
		{{ str_replace("\n", ', ', trim((string) $product->description)) }}. Ako je paket već uključen, produžava se; tokom besplatne prve godine paket počinje kad ona istekne.
		@break
@endswitch
