<?php

namespace App\Http\Controllers\Stranice;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Settings;
use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\Telefon;
use Illuminate\View\View;

class ZaFirmeController extends Controller
{
    public function __invoke(): View
    {
        $phone = (string) Settings::get('contact_phone', '');

        return view('stranice.za-firme', [
            'biznis' => Cenovnik::product(Product::BIZNIS_12M),
            'counter' => Cenovnik::counter(),
            'offerOpen' => Cenovnik::offerOpen(),
            'offer' => Cenovnik::offerText(),
            'freeListings' => Cenovnik::freeListings(),
            'phone' => Telefon::normalize($phone),
            'phoneText' => Telefon::display($phone),
            'title' => 'Za firme: Biznis paket i profil firme | loglasi.rs',
            'description' => 'Predstavite svoju firmu kupcima iz Loznice: profil sa logoom, radnim vremenom, mapom i svim oglasima na jednom mestu.',
            'canonical' => url_slash('za-firme'),
        ]);
    }
}
