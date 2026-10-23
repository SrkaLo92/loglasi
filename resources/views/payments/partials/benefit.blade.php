{{-- What the buyer gets, by product code. --}}
@use('App\Models\Product')
@switch($product->code)
	@case(Product::BUMP)
		Oglas se vraća na vrh liste, kao da je upravo postavljen. Oglas može da se podigne jednom u {{ config('payments.bump_cooldown_hours') }} sata.
		@break
	@case(Product::FEATURED_7)
	@case(Product::FEATURED_30)
		Oglas je istaknut {{ $product->duration_days ?: ($product->code === Product::FEATURED_30 ? 30 : 7) }} dana od uplate: prikazuje se iznad ostalih i ima oznaku „Istaknuto”. Ako je oglas već istaknut, isticanje se produžava.
		@break
	@case(Product::BIZNIS_12M)
		Biznis paket važi {{ ($product->duration_days ?: 365) >= 365 ? '12 meseci' : $product->duration_days.' dana' }} od uplate: profil firme, oznaka „Firma” i više oglasa. Ako je paket već aktivan, produžava se.
		@break
@endswitch
