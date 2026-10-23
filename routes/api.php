<?php

// API za mobilnu aplikaciju, ugovor: loglasi-mobile-app/api/openapi.yaml.
// Prefiks /api/v1 postavlja bootstrap/app.php. Svaki modul ima svoj fajl u routes/api/.

foreach (glob(__DIR__.'/api/*.php') as $file) {
    require $file;
}
