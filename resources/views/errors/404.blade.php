@use('App\Support\Stranice\PageText')
@include('errors.stranica', ['kod' => 404, 'naslov' => PageText::get('greska.404.naslov'), 'tekst' => PageText::get('greska.404.tekst'), 'seoTitle' => PageText::get('seo.404.title')])
