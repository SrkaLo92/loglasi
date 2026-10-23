<?php

namespace App\Console\Commands;

use App\Uvoz\Importer;
use App\Uvoz\WordPress;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class UvozWordpress extends Command
{
    protected $signature = 'uvoz:wordpress
        {--uploads= : Putanja do wp-content/uploads (slike se kopiraju na disk „public”)}
        {--dry-run : Proba: sve se izračuna i prijavi, ništa se ne upisuje}
        {--only= : Samo ovi delovi, zarezom: settings,users,categories,locations,firms,fields,listings,favorites,messages,gone,orders}
        {--since= : Samo izmene od ovog trenutka (npr. 2026-11-01 ili "2026-11-01 08:00"), za poslednji uvoz pred prelazak}';

    protected $description = 'Uvoz korisnika, firmi, kategorija, mesta, polja, oglasa, slika i poruka iz WordPress/Listivo baze';

    public function handle(): int
    {
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $unknown = array_diff($only, array_keys(Importer::STEPS));
        if ($unknown) {
            $this->error('Nepoznat deo za --only: '.implode(', ', $unknown).'. Dozvoljeno: '.implode(',', array_keys(Importer::STEPS)));

            return self::INVALID;
        }

        $since = null;
        if ($this->option('since')) {
            try {
                $since = Carbon::parse((string) $this->option('since'), config('app.timezone'));
            } catch (Throwable) {
                $this->error('Neispravan datum za --since: '.$this->option('since'));

                return self::INVALID;
            }
        }

        $uploads = $this->option('uploads') ? (string) $this->option('uploads') : null;
        if ($uploads !== null && ! is_dir($uploads)) {
            $this->error("Folder sa slikama ne postoji: {$uploads}");

            return self::INVALID;
        }

        WordPress::register();
        $wp = new WordPress;

        try {
            $wp->db()->getPdo();
            if (! $wp->hasTable('posts')) {
                $this->error('U bazi '.$wp->db()->getDatabaseName().' nema tabele '.$wp->prefix().'posts. Proverite WP_DB_DATABASE i WP_DB_PREFIX.');

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Nema veze sa WordPress bazom: '.$e->getMessage());

            return self::FAILURE;
        }

        $importer = new Importer($wp, (bool) $this->option('dry-run'), $since, $uploads);
        $importer->output = fn (string $line) => $this->line($line, null, 'v');

        $started = now();
        $this->info($importer->dryRun ? 'Proba uvoza (ništa se ne upisuje)...' : 'Uvoz iz WordPress-a...');

        $report = $importer->run($only);

        $this->table(['Šta', 'U WP', 'Uvezeno', 'Preskočeno', 'Greške'], $report->rows());

        $dir = (string) (config('uvoz.report_dir') ?: storage_path('app/uvoz'));
        $file = $dir.'/izvestaj-'.$started->format('Y-m-d-His').'.txt';
        for ($i = 2; is_file($file); $i++) {
            $file = $dir.'/izvestaj-'.$started->format('Y-m-d-His').'-'.$i.'.txt';
        }
        $report->write($file, [
            'Vreme' => $started->format('d.m.Y. H:i:s'),
            'Trajanje' => $started->diffForHumans(now(), true),
            'WordPress baza' => $wp->db()->getDatabaseName().' (prefiks '.$wp->prefix().')',
            'Režim' => $importer->dryRun ? 'proba (--dry-run), ništa nije upisano' : 'upis',
            'Delovi' => $only ? implode(', ', $only) : 'sve',
            'Od' => $since ? $since->format('d.m.Y. H:i') : 'sve',
            'Slike' => $uploads ?? 'nisu kopirane (bez --uploads)',
        ]);

        $this->line('Izveštaj: '.$file);

        if ($report->totalErrors() > 0) {
            $this->warn('Uvoz je završen sa greškama ('.$report->totalErrors().'). Detalji su u izveštaju.');
        } else {
            $this->info('Uvoz je završen bez grešaka.');
        }

        return self::SUCCESS;
    }
}
