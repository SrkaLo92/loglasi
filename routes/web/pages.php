<?php

use App\Http\Controllers\Pages\ContactController;
use App\Http\Controllers\Pages\FooterController;
use App\Http\Controllers\Pages\ForFirmsController;
use App\Http\Controllers\Pages\PricingController;
use App\Http\Controllers\Pages\TextPageController;
use Illuminate\Support\Facades\Route;

// Sadržajne strane, na istim adresama kao na sadašnjem sajtu.

Route::get('/cenovnik', PricingController::class)->name('pages.pricing');
Route::get('/za-firme', ForFirmsController::class)->name('pages.for-firms');

Route::get('/kontakt', [ContactController::class, 'show'])->name('pages.contact');
Route::post('/kontakt', [ContactController::class, 'send'])->middleware('throttle:20,1')->name('pages.contact.send');

Route::get('/o-nama', [TextPageController::class, 'show'])->defaults('strana', 'o_nama')->name('pages.about');
Route::get('/faq', [TextPageController::class, 'faq'])->name('pages.faq');
Route::get('/uslovi-koriscenja', [TextPageController::class, 'show'])->defaults('strana', 'uslovi')->name('pages.terms');
Route::get('/pravila-oglasavanja', [TextPageController::class, 'show'])->defaults('strana', 'pravila')->name('pages.rules');
Route::get('/brisanje-licnih-podataka', [TextPageController::class, 'show'])->defaults('strana', 'brisanje')->name('pages.data-deletion');
Route::get('/politika-privatnosti', [TextPageController::class, 'show'])->defaults('strana', 'privatnost')->name('pages.privacy');

// Pravni tekstovi za plaćene usluge (Zakon o zaštiti potrošača).
Route::get('/uslovi-placanja', [TextPageController::class, 'show'])->defaults('strana', 'placanje')->name('pages.payment-terms');
Route::get('/odustanak-od-ugovora', [TextPageController::class, 'show'])->defaults('strana', 'odustanak')->name('pages.withdrawal');
Route::get('/reklamacije', [TextPageController::class, 'show'])->defaults('strana', 'reklamacije')->name('pages.complaints');

Route::get('/podnozje/najnoviji-oglasi', [FooterController::class, 'latest'])->middleware('throttle:60,1')->name('pages.footer.latest');
