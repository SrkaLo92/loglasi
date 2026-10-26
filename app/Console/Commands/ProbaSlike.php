<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Listing;
use App\Services\ListingImages;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Test site only: gives the invented listings (@primer.loglasi.test) one to three photos
 * matching their category. Photos are downloaded once into storage/app/proba-slike
 * (loremflickr by keyword, picsum as a fallback) and go through the normal upload
 * pipeline (WebP, variants), so the site behaves as with real uploads.
 */
class ProbaSlike extends Command
{
    protected $signature = 'proba:slike
        {--sajt= : host of this site from APP_URL (safety check)}
        {--po-kategoriji=8 : photos downloaded per main category}
        {--najvise=3 : most photos per listing}';

    protected $description = 'Probni sajt: dodaje slike izmišljenim oglasima';

    private const EMAIL_DOMAIN = 'primer.loglasi.test';

    /** Search words for the photos, per root category slug. */
    private const KEYWORDS = [
        'automobili' => 'car', 'auto-delovi-i-oprema' => 'tire', 'motocikli' => 'motorcycle', 'kamioni' => 'truck',
        'bicikli-i-delovi' => 'bicycle', 'nekretnine' => 'house', 'mobilni-telefoni' => 'smartphone',
        'elektronika' => 'laptop', 'kucni-aparati' => 'kitchen', 'namestaj' => 'furniture', 'kuca-i-basta' => 'garden',
        'gradjevinarstvo' => 'bricks', 'alati-i-masine' => 'tools', 'poljoprivreda' => 'tractor', 'moda' => 'clothes',
        'za-decu' => 'toys', 'sport-i-rekreacija' => 'sport', 'muzicki-instrumenti' => 'guitar', 'kucni-ljubimci' => 'puppy',
        'knjige' => 'books', 'usluge' => 'workshop', 'poslovi' => 'office', 'besplatne-stvari' => 'boxes', 'ostalo' => 'antique',
    ];

    public function handle(ListingImages $images): int
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (ProbaPodaci::isLiveHost()) {
            $this->error('Ovo je pravi sajt (loglasi.rs). Komanda radi samo na probnom sajtu.');

            return self::FAILURE;
        }
        if ($host === '' || strtolower((string) $this->option('sajt')) !== $host) {
            $this->error("Za potvrdu dodaj --sajt={$host} (host iz APP_URL).");

            return self::FAILURE;
        }

        // Variants are made right away instead of waiting for the queue.
        config(['queue.default' => 'sync']);

        $categories = Category::query()->get(['id', 'parent_id', 'slug'])->keyBy('id');
        $max = max(1, (int) $this->option('najvise'));
        $pools = [];
        $added = 0;

        $listings = Listing::query()
            ->whereDoesntHave('images')
            ->whereHas('user', fn ($q) => $q->where('email', 'like', '%@'.self::EMAIL_DOMAIN))
            ->with('user');

        $bar = $this->output->createProgressBar((clone $listings)->count());

        $listings->chunkById(50, function ($chunk) use ($images, $categories, $max, &$pools, &$added, $bar) {
            foreach ($chunk as $listing) {
                $root = $this->rootSlug((int) $listing->category_id, $categories);
                $pools[$root] ??= $this->pool($root);
                $pool = $pools[$root];
                if ($pool === []) {
                    $bar->advance();

                    continue;
                }
                shuffle($pool);
                foreach (array_slice($pool, 0, mt_rand(1, min($max, count($pool)))) as $path) {
                    try {
                        $images->upload(new UploadedFile($path, basename($path), 'image/jpeg', null, true), $listing->user, $listing);
                        $added++;
                    } catch (Throwable $e) {
                        $this->warn("Oglas {$listing->id}: ".$e->getMessage());
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Dodato slika: {$added}.");

        return self::SUCCESS;
    }

    private function rootSlug(int $id, $categories): string
    {
        $category = $categories->get($id);
        for ($i = 0; $category && $category->parent_id && $i < 10; $i++) {
            $category = $categories->get($category->parent_id) ?? $category;
        }

        return $category->slug ?? 'ostalo';
    }

    /** @return list<string> local photo files for one main category, downloaded the first time */
    private function pool(string $root): array
    {
        $dir = storage_path('app/proba-slike/'.$root);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $keyword = self::KEYWORDS[$root] ?? 'object';
        $want = max(1, (int) $this->option('po-kategoriji'));
        for ($n = 1; $n <= $want; $n++) {
            $file = $dir.'/'.$n.'.jpg';
            if (is_file($file)) {
                continue;
            }
            foreach (["https://loremflickr.com/1200/900/{$keyword}?lock={$n}", "https://picsum.photos/seed/{$root}{$n}/1200/900"] as $url) {
                try {
                    $response = Http::timeout(20)->withOptions(['allow_redirects' => true])->get($url);
                    if ($response->successful() && @imagecreatefromstring($response->body()) !== false) {
                        file_put_contents($file, $response->body());

                        break;
                    }
                } catch (Throwable) {
                    // Try the next source.
                }
            }
        }

        return glob($dir.'/*.jpg') ?: [];
    }
}
