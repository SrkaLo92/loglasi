<?php

use App\Http\Controllers\Accounts\EmailVerificationController;
use App\Http\Controllers\Accounts\LoginController;
use App\Http\Controllers\Accounts\MyAccountController;
use App\Http\Controllers\Accounts\PasswordController;
use App\Http\Controllers\Accounts\RegistrationController;
use Illuminate\Support\Facades\Route;

// Nalozi: prijava, registracija, lozinka, potvrda emaila, „Moj nalog”.
// Linkovi se prave sa url_slash() i LoginPage::url(): /login-registracija/, /moj-nalog/...

Route::middleware('guest')->group(function () {
    // Jedina strana za prijavu i registraciju (adresa sa starog sajta), kartice ?tab=prijava|registracija.
    Route::get('/login-registracija', [LoginController::class, 'show'])->name('login');

    // GET /prijava/ i /registracija/ su 301 na nju; forme i dalje šalju POST na ove adrese.
    Route::get('/prijava', [LoginController::class, 'legacy'])->name('login.legacy');
    Route::post('/prijava', [LoginController::class, 'store'])->middleware('throttle:nalozi-login');

    Route::get('/registracija', [RegistrationController::class, 'legacy'])->name('register');
    Route::post('/registracija', [RegistrationController::class, 'store'])->middleware('throttle:nalozi-register');

    Route::get('/zaboravljena-lozinka', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/zaboravljena-lozinka', [PasswordController::class, 'email'])->middleware('throttle:nalozi-password')->name('password.email');
    Route::get('/nova-lozinka/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/nova-lozinka', [PasswordController::class, 'update'])->middleware('throttle:nalozi-password')->name('password.update');
});

// Link iz mejla radi i kad korisnik nije prijavljen (npr. otvori ga na telefonu).
Route::get('/potvrda-emaila/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->whereNumber('id')
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::post('/odjava', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/potvrda-emaila', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/potvrda-emaila/ponovo', [EmailVerificationController::class, 'send'])->middleware('throttle:3,10')->name('verification.send');

    Route::get('/moj-nalog', [MyAccountController::class, 'show'])->name('account');
    Route::get('/moj-nalog/podesavanja', [MyAccountController::class, 'settings'])->name('account.settings');
    Route::get('/moj-nalog/brisanje', [MyAccountController::class, 'confirmDelete'])->name('account.delete.confirm');

    Route::middleware('throttle:nalozi-account')->group(function () {
        Route::put('/moj-nalog/podaci', [MyAccountController::class, 'updateProfile'])->name('account.details');
        Route::put('/moj-nalog/lozinka', [MyAccountController::class, 'updatePassword'])->name('account.password');
        Route::post('/moj-nalog/slika', [MyAccountController::class, 'updateAvatar'])->name('account.avatar');
        Route::delete('/moj-nalog/slika', [MyAccountController::class, 'destroyAvatar'])->name('account.avatar.delete');
        Route::delete('/moj-nalog/brisanje', [MyAccountController::class, 'destroy'])->name('account.delete');
    });
});
