<?php

// API for the mobile app, contract: loglasi-mobile-app/api/openapi.yaml.
// The /api/v1 prefix is set in bootstrap/app.php. Each module has its own file in routes/api/.

foreach (glob(__DIR__.'/api/*.php') as $file) {
    require $file;
}
