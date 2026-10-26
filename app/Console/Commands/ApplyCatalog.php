<?php

namespace App\Console\Commands;

use App\Support\Catalog\CatalogDefinition;
use Illuminate\Console\Command;

/**
 * Applies the catalog definition (database/seeders/data/catalog): fields, options, the fields of every
 * category, stored values moved to the new options. Run after every WordPress import and after editing
 * those files; field changes made in the admin to the fields listed there are reset.
 */
class ApplyCatalog extends Command
{
    protected $signature = 'catalog:apply {--dry-run : Count the changes, change nothing}';

    protected $description = 'Make fields, options and category fields match the catalog definition';

    /** Old name, kept so existing server scripts keep working. */
    protected $aliases = ['katalog:uredi'];

    public function handle(CatalogDefinition $definition): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = $definition->apply($dry);

        foreach ($result['notes'] as $note) {
            $this->warn($note);
        }
        foreach ($result['counts'] as $what => $n) {
            $this->line(sprintf('%-45s %d', $what, $n));
        }
        $this->info($dry ? 'Probno: ništa nije promenjeno.' : 'Katalog je uređen.');

        return self::SUCCESS;
    }
}
