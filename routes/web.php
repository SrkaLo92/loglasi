<?php

// Public pages of the site. Each module adds its routes in its own file in routes/web/
// (e.g. routes/web/listing.php), so parallel work does not collide in this file.

foreach (glob(__DIR__.'/web/*.php') as $file) {
    require $file;
}
