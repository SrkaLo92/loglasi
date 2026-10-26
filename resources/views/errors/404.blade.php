@use('App\Support\Pages\PageText')
@include('errors.page', ['code' => 404, 'title' => PageText::get('greska.404.naslov'), 'text' => PageText::get('greska.404.tekst'), 'seoTitle' => PageText::get('seo.404.title')])
