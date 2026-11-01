<?php

namespace Tests\Feature\Info;

use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use Tests\TestCase;

class DraftApiTest extends TestCase
{
    private array $draft = [
        'title' => 'Radovi na putu ka Banji Koviljači',
        'summary' => 'Kratak sažetak našim rečima.',
        'source_name' => 'Lokalni portal',
        'source_url' => 'https://primer.rs/vesti/radovi?utm_source=fb',
        'topic' => 'saobracaj',
    ];

    public function test_closed_without_token(): void
    {
        config(['info.drafts_token' => '']);
        $this->postJson('/api/v1/info/drafts', $this->draft)->assertUnauthorized();

        config(['info.drafts_token' => 'tajna']);
        $this->withToken('pogresno')->postJson('/api/v1/info/drafts', $this->draft)->assertUnauthorized();
        $this->assertSame(0, InfoPost::query()->count());
    }

    public function test_creates_pending_draft_once(): void
    {
        config(['info.drafts_token' => 'tajna']);

        $this->withToken('tajna')->postJson('/api/v1/info/drafts', $this->draft)->assertCreated()->assertJson(['status' => 'created']);
        $this->withToken('tajna')->postJson('/api/v1/info/drafts', $this->draft + ['title' => 'Drugi naslov'])
            ->assertOk()->assertJson(['status' => 'duplicate']);

        $post = InfoPost::query()->sole();
        $this->assertSame('pending', $post->status);
        $this->assertSame('automation', $post->origin);
        $this->assertTrue($post->isSourceSummary());
    }

    public function test_water_notice_is_stored_hidden(): void
    {
        config(['info.drafts_token' => 'tajna']);
        $notice = ['kind' => 'water', 'summary' => 'Bez vode u ulici Jovana Cvijića.', 'place' => 'Loznica',
            'starts_at' => '2026-11-05 08:00', 'ends_at' => '2026-11-05 14:00',
            'source_name' => 'JKP Vodovod', 'source_url' => 'https://primer.rs/obavestenje'];

        $this->withToken('tajna')->postJson('/api/v1/info/drafts', $notice)->assertCreated();
        $this->withToken('tajna')->postJson('/api/v1/info/drafts', $notice)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertFalse(InfoServiceNotice::query()->sole()->published);
    }
}
