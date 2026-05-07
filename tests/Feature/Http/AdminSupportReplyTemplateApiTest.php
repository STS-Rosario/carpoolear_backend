<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\SupportReplyTemplate;
use STS\Models\User;
use Tests\TestCase;

class AdminSupportReplyTemplateApiTest extends TestCase
{
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_guest_cannot_list_reply_templates(): void
    {
        $this->getJson('api/admin/support/reply-templates')->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_reply_templates(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->saveQuietly();

        $this->actingAs($user, 'api');
        $this->getJson('api/admin/support/reply-templates')->assertForbidden();
    }

    public function test_admin_lists_reply_templates_newest_first(): void
    {
        $admin = $this->adminUser();
        $older = SupportReplyTemplate::create([
            'name' => 'Older',
            'short_description' => null,
            'body_markdown' => 'Body old',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $newer = SupportReplyTemplate::create([
            'name' => 'Newer',
            'short_description' => 'Short',
            'body_markdown' => 'Body new',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/support/reply-templates')->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
        $rows = collect($response->json('data'));
        $this->assertGreaterThanOrEqual(2, $rows->count());
        $newerIdx = $rows->search(fn (array $r): bool => (int) $r['id'] === $newer->id);
        $olderIdx = $rows->search(fn (array $r): bool => (int) $r['id'] === $older->id);
        $this->assertNotFalse($newerIdx);
        $this->assertNotFalse($olderIdx);
        $this->assertLessThan($olderIdx, $newerIdx);
    }

    public function test_admin_stores_reply_template_with_audit_fields(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/reply-templates', [
            'name' => 'Welcome',
            'short_description' => 'Greeting',
            'body_markdown' => 'Hola {{nombre}}',
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Welcome', $data['name']);
        $this->assertSame('Greeting', $data['short_description']);
        $this->assertSame('Hola {{nombre}}', $data['body_markdown']);
        $this->assertSame($admin->id, $data['created_by']);
        $this->assertSame($admin->id, $data['updated_by']);

        $this->assertDatabaseHas('support_reply_templates', [
            'id' => $data['id'],
            'name' => 'Welcome',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/support/reply-templates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'body_markdown']);
    }

    public function test_admin_shows_reply_template_with_nested_users(): void
    {
        $admin = $this->adminUser();
        $template = SupportReplyTemplate::create([
            'name' => 'T1',
            'short_description' => null,
            'body_markdown' => 'X',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/support/reply-templates/'.$template->id)->assertOk();
        $data = $response->json('data');
        $this->assertSame($template->id, $data['id']);
        $this->assertArrayHasKey('creator', $data);
        $this->assertArrayHasKey('updater', $data);
        $this->assertSame($admin->id, $data['creator']['id']);
    }

    public function test_admin_updates_reply_template_sets_updated_by(): void
    {
        $admin = $this->adminUser();
        $other = $this->adminUser();
        $template = SupportReplyTemplate::create([
            'name' => 'Original',
            'short_description' => null,
            'body_markdown' => 'A',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($other, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->putJson('api/admin/support/reply-templates/'.$template->id, [
            'name' => 'Renamed',
            'short_description' => 'Desc',
            'body_markdown' => 'B',
        ])->assertOk();

        $data = $response->json('data');
        $this->assertSame('Renamed', $data['name']);
        $this->assertSame($admin->id, $data['created_by']);
        $this->assertSame($other->id, $data['updated_by']);
    }

    public function test_admin_destroys_reply_template(): void
    {
        $admin = $this->adminUser();
        $template = SupportReplyTemplate::create([
            'name' => 'Del',
            'short_description' => null,
            'body_markdown' => 'Z',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->deleteJson('api/admin/support/reply-templates/'.$template->id)->assertNoContent();
        $this->assertDatabaseMissing('support_reply_templates', ['id' => $template->id]);
    }

    public function test_admin_duplicates_reply_template_with_copy_suffix(): void
    {
        $admin = $this->adminUser();
        $template = SupportReplyTemplate::create([
            'name' => 'Source',
            'short_description' => 'Hi',
            'body_markdown' => 'Body {{nombreCompleto}}',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/support/reply-templates/'.$template->id.'/duplicate')->assertCreated();
        $data = $response->json('data');
        $this->assertNotSame($template->id, $data['id']);
        $this->assertSame('Source (copy)', $data['name']);
        $this->assertSame('Hi', $data['short_description']);
        $this->assertSame('Body {{nombreCompleto}}', $data['body_markdown']);
        $this->assertSame($admin->id, $data['created_by']);
    }
}
