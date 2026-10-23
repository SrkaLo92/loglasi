@use('App\Support\Stranice\PageText')
@include('errors.stranica', ['kod' => 410, 'naslov' => PageText::get('greska.410.naslov'), 'tekst' => PageText::get('greska.410.tekst')])
