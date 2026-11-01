<?php

namespace Tests\Feature\Info;

use App\Filament\Resources\Info\Pages\ManageInfoPosts;
use App\Models\InfoBusiness;
use App\Models\InfoPost;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class InfoAdminTest extends TestCase
{
    use ActsAsStaff;

    public function test_info_pages_render_in_the_admin(): void
    {
        InfoPost::query()->create(['type' => 'news', 'status' => 'pending', 'origin' => 'citizen', 'title' => 'Vest na čekanju', 'summary' => 'Kratak tekst vesti.']);
        $business = InfoBusiness::query()->create(['name' => 'Pekara Klas', 'status' => 'listed']);
        $business->requests()->create(['kind' => 'correct', 'name' => 'Vlasnik', 'email' => 'v@example.com']);

        $this->signIn($this->admin());

        foreach (['info-posts', 'info-service-notices', 'info-pharmacy-duties', 'info-sources', 'info-businesses', 'info-business-requests'] as $page) {
            $this->get('/admin/info/'.$page)->assertOk();
        }
        $this->get('/admin/info/info-posts')->assertSee('Vest na čekanju');
    }

    public function test_editor_publishes_a_pending_post(): void
    {
        $post = InfoPost::query()->create(['type' => 'news', 'status' => 'pending', 'origin' => 'citizen', 'title' => 'Vest za objavu', 'summary' => 'Kratak tekst vesti.']);
        $admin = $this->admin();
        $this->signIn($admin);

        Livewire::test(ManageInfoPosts::class)->callTableAction('publish', $post);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertSame($admin->id, $post->reviewed_by);
        $this->assertNotNull($post->published_at);
    }
}
