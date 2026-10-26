@use('App\Support\Pages\PageText')
@include('errors.page', ['code' => 410, 'title' => PageText::get('greska.410.naslov'), 'text' => PageText::get('greska.410.tekst')])
