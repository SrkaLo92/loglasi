<?php

namespace App\Support\Import;

use RuntimeException;

/** A table that cannot be read; the message is written for the user. */
class ImportFailed extends RuntimeException {}
