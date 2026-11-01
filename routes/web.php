<?php

// Public pages of the site. Each module adds its routes in its own file in routes/web/
// (e.g. routes/web/listing.php), so parallel work does not collide in this file.

// The info portal answers on its own host and is registered first: Laravel matches routes in
// registration order, and the site's routes have no host, so they would otherwise answer there too.
require __DIR__.'/info.php';

foreach (glob(__DIR__.'/web/*.php') as $file) {
    require $file;
}
