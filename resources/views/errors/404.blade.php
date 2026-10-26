@use('App\Support\Pages\PageText')
@include('errors.page', ['kod' => 404, 'naslov' => PageText::get('greska.404.naslov'), 'tekst' => PageText::get('greska.404.tekst'), 'seoTitle' => PageText::get('seo.404.title')])
