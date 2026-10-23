<?php

use App\Http\Controllers\Stranice\CenovnikController;
use App\Http\Controllers\Stranice\KontaktController;
use App\Http\Controllers\Stranice\TekstController;
use App\Http\Controllers\Stranice\ZaFirmeController;
use Illuminate\Support\Facades\Route;

// Sadržajne strane, na istim adresama kao na sadašnjem sajtu.

Route::get('/cenovnik', CenovnikController::class)->name('stranice.cenovnik');
Route::get('/za-firme', ZaFirmeController::class)->name('stranice.za-firme');

Route::get('/kontakt', [KontaktController::class, 'show'])->name('stranice.kontakt');
Route::post('/kontakt', [KontaktController::class, 'send'])->middleware('throttle:20,1')->name('stranice.kontakt.posalji');

Route::get('/o-nama', [TekstController::class, 'show'])->defaults('strana', 'o_nama')->name('stranice.o-nama');
Route::get('/faq', [TekstController::class, 'faq'])->name('stranice.faq');
Route::get('/uslovi-koriscenja', [TekstController::class, 'show'])->defaults('strana', 'uslovi')->name('stranice.uslovi');
Route::get('/politika-privatnosti', [TekstController::class, 'show'])->defaults('strana', 'privatnost')->name('stranice.privatnost');
