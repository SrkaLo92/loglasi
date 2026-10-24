<?php

namespace Tests\Feature\Notifications;

use App\Models\SavedSearch;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
    }

    public function test_notification_switches_change_only_the_keys_that_are_sent(): void
    {
        $this->getJson('/api/v1/me/notification-settings')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me/notification-settings')->assertOk()
            ->assertJsonPath('items.0.key', 'messages')
            ->assertJsonPath('items.0.enabled', true)
            ->assertJsonPath('items.3.key', 'newsletter');

        $this->patchJson('/api/v1/me/notification-settings', ['newsletter' => true])->assertOk()
            ->assertJsonPath('items.0.enabled', true)
            ->assertJsonPath('items.3.enabled', true);
        $this->patchJson('/api/v1/me/notification-settings', ['messages' => false])->assertOk()
            ->assertJsonPath('items.0.enabled', false)
            ->assertJsonPath('items.3.enabled', true);
        $this->patchJson('/api/v1/me/notification-settings', ['messages' => 'možda'])->assertStatus(422);
    }

    public function test_saved_searches_list_add_rename_and_delete(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/me/saved-searches', ['keyword' => 'golf', 'kategorija' => 'automobili', 'name' => 'Golf'])
            ->assertCreated()->assertJsonPath('name', 'Golf')->assertJsonPath('notify', true)->json('id');
        $this->postJson('/api/v1/me/saved-searches', ['keyword' => 'golf', 'kategorija' => 'automobili'])->assertOk()->assertJsonPath('id', $id);

        $this->getJson('/api/v1/me/saved-searches')->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.query.keyword', 'golf')
            ->assertJsonStructure(['items' => [['id', 'name', 'query', 'notify', 'url', 'created_at']], 'max', 'alerts_on']);

        $this->patchJson('/api/v1/me/saved-searches/'.$id, ['notify' => false, 'name' => 'Golf dizel'])->assertOk()
            ->assertJsonPath('notify', false)->assertJsonPath('name', 'Golf dizel');

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson('/api/v1/me/saved-searches/'.$id)->assertForbidden();

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/me/saved-searches/'.$id)->assertNoContent();
        $this->assertSame(0, SavedSearch::count());
    }
}
