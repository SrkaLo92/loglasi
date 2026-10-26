@use('App\Support\Pages\PageText')
@include('errors.page', ['kod' => 410, 'naslov' => PageText::get('greska.410.naslov'), 'tekst' => PageText::get('greska.410.tekst')])
