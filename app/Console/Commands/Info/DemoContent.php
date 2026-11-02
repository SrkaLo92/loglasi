<?php

namespace App\Console\Commands\Info;

use App\Models\InfoAd;
use App\Models\InfoBusiness;
use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Sample posts, events, notices and one ad for a local preview of the info portal. Never on production. */
class DemoContent extends Command
{
    protected $signature = 'info:demo {--remove : delete the sample content again} {--photos : download sample photos from picsum.photos}';

    protected $description = 'Info portal: add sample content for a local preview (refuses on production)';

    private const MARK = 'loglasi-info-primer';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Not on production.');

            return self::FAILURE;
        }

        InfoPost::query()->where('slug', 'like', 'primer-%')->delete();
        InfoServiceNotice::query()->where('source_name', self::MARK)->delete();
        InfoAd::query()->where('text', 'like', '%(primer)%')->delete();
        InfoPharmacyDuty::query()->where('name', 'like', '%(primer)%')->delete();
        InfoBusiness::query()->whereNotNull('featured_until')->where('opening_hours', 'like', 'Pon–pet 08–17%')
            ->update(['featured_until' => null, 'opening_hours' => null, 'gallery' => null]);
        if ($this->option('remove')) {
            $this->info('Sample content removed.');

            return self::SUCCESS;
        }

        $now = now();
        $posts = [
            ['news', 'drustvo', 'Centar grada dobija nove klupe i drveće do kraja novembra', 'Radovi u pešačkoj zoni počinju u ponedeljak i izvode se deo po deo, pa će lokali sve vreme raditi normalno.', true, 2],
            ['news', 'saobracaj', 'Promenjen red vožnje gradskih linija za Banju Koviljaču', 'Od ponedeljka autobusi za Banju Koviljaču polaze na svakih četrdeset minuta, a poslednji polazak je u 22 časa.', false, 3],
            ['news', 'obrazovanje', 'Upis u predškolsko za januar počinje sledeće nedelje', 'Roditelji mogu da predaju prijave u svim vrtićima u gradu ili elektronski, do kraja novembra.', false, 5],
            ['news', 'sport', 'Škola fudbala prima nove polaznike od šest godina', 'Treninzi se održavaju tri puta nedeljno na gradskom stadionu, a prvi mesec je besplatan za nove polaznike.', false, 7],
            ['news', 'kultura', 'Biblioteka otvara čitaonicu i nedeljom pre podne', 'Čitaonica će nedeljom raditi od 9 do 13 časova, uz besplatan internet i novu policu sa lokalnom istorijom.', false, 9],
            ['news', 'privreda', 'Proizvođači iz okoline dobijaju besplatne tezge subotom', 'Na zelenoj pijaci je subotom rezervisan red tezgi za male proizvođače iz sela oko Loznice.', false, 26],
            ['story', 'kultura', 'Jutro na Drini: ribari koji dolaze pre sunca', 'Proveli smo jedno jutro sa ljudima koji ovu reku znaju bolje od bilo koje mape.', false, 30],
            ['guide', 'zdravlje', 'Kako do dežurne apoteke posle 20 časova', 'Svake večeri jedna apoteka u gradu radi do jutra. Ovde je raspored i šta da ponesete.', false, 50],
        ];
        foreach ($posts as $i => [$type, $topic, $title, $summary, $featured, $hoursAgo]) {
            InfoPost::query()->create([
                'type' => $type, 'status' => 'published', 'origin' => 'manual', 'topic' => $topic, 'title' => $title,
                'slug' => 'primer-'.($i + 1).'-'.str($title)->slug(), 'summary' => $summary, 'featured' => $featured,
                'place' => 'Loznica', 'image_path' => $this->photo('post-'.$i), 'published_at' => $now->copy()->subHours($hoursAgo),
            ]);
        }
        $events = [
            ['kultura', 'Jesenji koncert hora', 6, '20:00', 'Vukov dom kulture', 'Loznica', true],
            ['privreda', 'Sajam domaće hrane', 7, '09:00', 'Gradski trg', 'Loznica', false],
            ['drustvo', 'Šetnja kroz park u Banji', 7, '11:00', 'Park u Banji Koviljači', 'Banja Koviljača', false],
            ['sport', 'Utakmica: FK Loznica protiv Mačve', 8, '14:00', 'Gradski stadion', 'Loznica', false],
            ['kultura', 'Pozorišna predstava za decu', 10, '18:00', 'Vukov dom kulture', 'Loznica', false],
        ];
        foreach ($events as $i => [$topic, $title, $inDays, $time, $venue, $place, $featured]) {
            InfoPost::query()->create([
                'type' => 'event', 'status' => 'published', 'origin' => 'manual', 'topic' => $topic, 'title' => $title,
                'slug' => 'primer-dogadjaj-'.($i + 1), 'summary' => 'Ulaz slobodan. Dođite na vreme, broj mesta je ograničen.',
                'event_starts_at' => today()->addDays($inDays)->setTimeFromTimeString($time), 'event_venue' => $venue, 'place' => $place,
                'featured' => $featured, 'image_path' => $featured ? $this->photo('event-'.$i) : null, 'published_at' => $now->copy()->subDay(),
            ]);
        }

        InfoPharmacyDuty::query()->whereDate('date', today())->delete();
        InfoPharmacyDuty::query()->create(['date' => today(), 'name' => 'Apoteka Zdravlje (primer)', 'address' => 'Kneza Miloša 12', 'phone' => '015 000 000', 'hours' => '20:00 do 07:00']);
        foreach ([[1, 'Lešnica', 'Cerska, Šabačka, Kralja Petra I od broja 40 do kraja', '08:30', '13:00'], [1, 'Jadranska Lešnica', 'Ceo deo naselja uz regionalni put', '08:30', '13:00'], [2, 'Banja Koviljača', 'Vojvode Putnika, Rudnička', '09:00', '11:30']] as $j => [$d, $place, $area, $from, $to]) {
            InfoServiceNotice::query()->create([
                'kind' => 'power', 'municipality' => 'Loznica', 'place' => $place, 'area' => $area,
                'starts_at' => today()->addDays($d)->setTimeFromTimeString($from), 'ends_at' => today()->addDays($d)->setTimeFromTimeString($to),
                'source_name' => self::MARK, 'external_hash' => sha1(self::MARK.$j), 'published' => true,
            ]);
        }

        $featured = InfoBusiness::query()->listed()->orderBy('id')->limit(2)->get();
        $featured->each(fn (InfoBusiness $b, int $k) => $b->update(['featured_until' => now()->addMonth(), 'opening_hours' => "Pon–pet 08–17\nSubota 08–13\nNedelja zatvoreno", 'gallery' => array_values(array_filter([$this->photo('firm-'.$k.'-a'), $this->photo('firm-'.$k.'-b'), $this->photo('firm-'.$k.'-c')]))]));
        if ($first = $featured->first()) {
            InfoAd::query()->create(['slot' => 'home_middle', 'info_business_id' => $first->id, 'title' => $first->name.': svratite ove nedelje', 'text' => 'Ovo je primer reklame na naslovnoj (primer)', 'starts_on' => today()]);
            InfoAd::query()->create(['slot' => 'article_bottom', 'info_business_id' => $first->id, 'title' => $first->name, 'text' => ($first->address ?: 'Loznica').' (primer)', 'starts_on' => today()]);
            InfoAd::query()->create(['slot' => 'today_sponsor', 'info_business_id' => $first->id, 'title' => $first->name, 'text' => '(primer)', 'starts_on' => today()]);
        }

        $this->info('Sample content added: '.count($posts).' posts, '.count($events).' events, pharmacy, 3 power notices, '.$featured->count().' featured firms, 3 ads.');

        return self::SUCCESS;
    }

    /** A sample photo on the public disk (only with --photos; a failed download just means no photo). */
    private function photo(string $seed): ?string
    {
        if (! $this->option('photos')) {
            return null;
        }
        $path = 'info-primer/'.$seed.'.jpg';
        if (! Storage::disk('public')->exists($path)) {
            try {
                $response = Http::timeout(15)->get('https://picsum.photos/seed/loglasi-'.$seed.'/1200/800');
            } catch (\Throwable) {
                return null;
            }
            if (! $response->successful()) {
                return null;
            }
            Storage::disk('public')->put($path, $response->body());
        }

        return $path;
    }
}
