<?php

use Illuminate\Support\Facades\Route;

// Javne strane sajta. Svaki modul dodaje svoje rute u svoj fajl u routes/web/
// (npr. routes/web/oglasi.php), da se paralelni rad ne sudara u ovom fajlu.

foreach (glob(__DIR__.'/web/*.php') as $file) {
    require $file;
}
