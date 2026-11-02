<?php

namespace Tests\Feature\Info;

use App\Models\InfoBusiness;
use App\Models\InfoPost;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InfoPortalTest extends TestCase
{
    private function info(string $path = ''): string
    {
        return 'http://'.config('info.domain').'/'.ltrim($path, '/');
    }

    public function test_portal_is_off_until_enabled(): void
    {
        config(['info.enabled' => false]);

        $this->get($this->info())->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('info-block.css', false);
    }

    public function test_home_and_sections_render_when_enabled(): void
    {
        config(['info.enabled' => true]);
        $post = InfoPost::query()->create([
            'type' => 'news', 'status' => 'published', 'origin' => 'manual',
            'title' => 'Počinje sajam u Loznici', 'summary' => 'Kratak opis sajma koji počinje u subotu.',
            'topic' => 'privreda',
        ]);
        InfoPost::query()->create([
            'type' => 'news', 'status' => 'pending', 'origin' => 'automation',
            'title' => 'Vest koja čeka pregled', 'summary' => 'Ovo ne sme da se vidi pre odobrenja.',
            'source_name' => 'Primer', 'source_url' => 'https://primer.rs/vest',
        ]);

        $this->get($this->info())->assertOk()->assertSee('Počinje sajam u Loznici')->assertDontSee('Vest koja čeka pregled');
        $this->get($this->info('vesti/'))->assertOk()->assertSee('Počinje sajam u Loznici');
        $this->get($post->url())->assertOk()->assertSee('Kratak opis sajma');
        foreach (['desavanja/', 'price/', 'vodici/', 'servisne-informacije/', 'firme/', 'posalji-vest/', 'robots.txt', 'sitemap.xml'] as $path) {
            $this->get($this->info($path))->assertOk();
        }
    }

    public function test_pending_post_is_not_public(): void
    {
        config(['info.enabled' => true]);
        $post = InfoPost::query()->create([
            'type' => 'news', 'status' => 'pending', 'origin' => 'citizen',
            'title' => 'Neodobrena vest', 'summary' => 'Tekst koji još nije odobren.',
        ]);

        $this->get($post->url())->assertNotFound();
    }

    public function test_other_paths_on_the_info_host_go_to_the_main_site(): void
    {
        config(['info.enabled' => true]);

        $this->get($this->info('oglasi/'))->assertStatus(301)
            ->assertRedirect(rtrim(config('info.main_url'), '/').'/oglasi/');
    }

    public function test_citizen_submission_waits_for_the_editor(): void
    {
        config(['info.enabled' => true]);

        $this->post($this->info('posalji-vest/'), [
            'type' => 'event', 'title' => 'Koncert u Vukovom domu', 'summary' => 'Koncert hora u subotu uveče, ulaz slobodan.',
            'event_date' => '2026-11-08', 'event_time' => '20:00', 'place' => 'Loznica',
            'submitter_name' => 'Marko', 'submitter_email' => 'marko@example.com', 'consent' => '1',
        ])->assertRedirect();

        $post = InfoPost::query()->sole();
        $this->assertSame('pending', $post->status);
        $this->assertSame('citizen', $post->origin);
        $this->assertSame('2026-11-08 20:00', $post->event_starts_at->timezone('Europe/Belgrade')->format('Y-m-d H:i'));
    }

    public function test_submission_honeypot_creates_nothing(): void
    {
        config(['info.enabled' => true]);

        $this->post($this->info('posalji-vest/'), [
            'type' => 'news', 'title' => 'Spam naslov', 'summary' => 'Spam tekst koji je dovoljno dugačak.',
            'submitter_name' => 'Bot', 'submitter_email' => 'bot@example.com', 'consent' => '1', 'website_url' => 'http://spam',
        ])->assertRedirect();

        $this->assertSame(0, InfoPost::query()->count());
    }

    public function test_directory_lists_businesses_and_takes_requests(): void
    {
        config(['info.enabled' => true]);
        $business = InfoBusiness::query()->create(['name' => 'Pekara Zlatni klas', 'category' => 'Pekare', 'address' => 'Glavna 1, Loznica', 'status' => 'listed']);
        InfoBusiness::query()->create(['name' => 'Skrivena firma', 'category' => 'Pekare', 'status' => 'hidden']);

        $this->get($this->info('firme/'))->assertOk()->assertSee('Pekara Zlatni klas')->assertDontSee('Skrivena firma');
        $this->get($this->info('firme/?q=zlatni'))->assertOk()->assertSee('Pekara Zlatni klas');
        $this->get($business->url())->assertOk()->assertSee('Glavna 1, Loznica');

        $this->post($business->url().'zahtev/', [
            'kind' => 'claim', 'name' => 'Vlasnik', 'email' => 'vlasnik@example.com',
        ])->assertRedirect();

        $this->assertSame('claim', $business->requests()->sole()->kind);
    }

    public function test_assets_come_from_the_portal_host_even_with_a_forced_root_url(): void
    {
        config(['info.enabled' => true]);
        URL::forceRootUrl('https://loglasi.example');

        $html = $this->get($this->info('/'))->assertOk()->getContent();

        $this->assertStringContainsString('http://'.config('info.domain').'/css/info.css', $html);
        $this->assertStringNotContainsString('https://loglasi.example/css/info.css', $html);
    }

    public function test_post_plays_our_youtube_clip_and_links_other_platforms(): void
    {
        config(['info.enabled' => true]);
        $yt = InfoPost::query()->create(['type' => 'story', 'status' => 'published', 'topic' => 'kultura', 'title' => 'Vuk Karadžić i Tršić',
            'summary' => 'Naš kratki film o Tršiću.', 'video_url' => 'https://youtube.com/shorts/AbCdEfGhIjK']);
        $ig = InfoPost::query()->create(['type' => 'story', 'status' => 'published', 'topic' => 'kultura', 'title' => 'Jesen u Banji',
            'summary' => 'Klip sa Instagrama.', 'video_url' => 'https://www.instagram.com/reel/xyz/']);

        $this->assertSame('AbCdEfGhIjK', $yt->youtubeId());
        $this->get($yt->url())->assertOk()->assertSee('youtube-nocookie.com/embed/AbCdEfGhIjK', false)->assertSee('inf-video--tall', false);
        $this->get($ig->url())->assertOk()->assertSee('Pogledajte video na Instagramu')->assertDontSee('youtube-nocookie', false);
    }
}
