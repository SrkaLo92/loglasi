<?php

use App\Http\Controllers\Nalozi\LozinkaController;
use App\Http\Controllers\Nalozi\NalogController;
use App\Http\Controllers\Nalozi\PotvrdaEmailaController;
use App\Http\Controllers\Nalozi\PrijavaController;
use App\Http\Controllers\Nalozi\RegistracijaController;
use Illuminate\Support\Facades\Route;

// Nalozi: prijava, registracija, lozinka, potvrda emaila, „Moj nalog”.
// Linkovi se prave sa url_slash() i LoginPage::url(): /login-registracija/, /moj-nalog/...

Route::middleware('guest')->group(function () {
    // Jedina strana za prijavu i registraciju (adresa sa starog sajta), kartice ?tab=prijava|registracija.
    Route::get('/login-registracija', [PrijavaController::class, 'show'])->name('login');

    // GET /prijava/ i /registracija/ su 301 na nju; forme i dalje šalju POST na ove adrese.
    Route::get('/prijava', [PrijavaController::class, 'legacy'])->name('login.legacy');
    Route::post('/prijava', [PrijavaController::class, 'store'])->middleware('throttle:nalozi-login');

    Route::get('/registracija', [RegistracijaController::class, 'legacy'])->name('register');
    Route::post('/registracija', [RegistracijaController::class, 'store'])->middleware('throttle:nalozi-register');

    Route::get('/zaboravljena-lozinka', [LozinkaController::class, 'request'])->name('password.request');
    Route::post('/zaboravljena-lozinka', [LozinkaController::class, 'email'])->middleware('throttle:nalozi-password')->name('password.email');
    Route::get('/nova-lozinka/{token}', [LozinkaController::class, 'reset'])->name('password.reset');
    Route::post('/nova-lozinka', [LozinkaController::class, 'update'])->middleware('throttle:nalozi-password')->name('password.update');
});

// Link iz mejla radi i kad korisnik nije prijavljen (npr. otvori ga na telefonu).
Route::get('/potvrda-emaila/{id}/{hash}', [PotvrdaEmailaController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->whereNumber('id')
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/odjava', [PrijavaController::class, 'destroy'])->name('logout');

    Route::get('/potvrda-emaila', [PotvrdaEmailaController::class, 'notice'])->name('verification.notice');
    Route::post('/potvrda-emaila/ponovo', [PotvrdaEmailaController::class, 'send'])->middleware('throttle:3,10')->name('verification.send');

    Route::get('/moj-nalog', [NalogController::class, 'show'])->name('nalog');
    Route::get('/moj-nalog/brisanje', [NalogController::class, 'confirmDelete'])->name('nalog.brisanje.potvrda');

    Route::middleware('throttle:nalozi-account')->group(function () {
        Route::put('/moj-nalog/podaci', [NalogController::class, 'updateProfile'])->name('nalog.podaci');
        Route::put('/moj-nalog/lozinka', [NalogController::class, 'updatePassword'])->name('nalog.lozinka');
        Route::post('/moj-nalog/slika', [NalogController::class, 'updateAvatar'])->name('nalog.slika');
        Route::delete('/moj-nalog/slika', [NalogController::class, 'destroyAvatar'])->name('nalog.slika.brisanje');
        Route::delete('/moj-nalog/brisanje', [NalogController::class, 'destroy'])->name('nalog.brisanje');
    });
});
