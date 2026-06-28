<?php

namespace Tests\Feature\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use STS\Http\Controllers\Api\Admin\ManualIdentityValidationController;
use STS\Http\Middleware\UserAdmin;
use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\SupportTicket;
use STS\Models\User;
use STS\Notifications\ManualIdentityValidationReviewNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class AdminManualIdentityValidationControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_returns_only_data_key_and_full_row_shape(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Manual User']);
        ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/manual-identity-validations')->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));

        $row = collect($response->json('data'))->firstWhere('user_id', $user->id);
        $this->assertNotNull($row);
        $this->assertSame([
            'id',
            'user_id',
            'user_name',
            'paid_at',
            'submitted_at',
            'manual_validation_started_at',
            'paid',
            'review_status',
            'has_images',
        ], array_keys($row));
        $this->assertSame('Manual User', $row['user_name']);
        $this->assertTrue($row['paid']);
        $this->assertFalse($row['has_images']);
    }

    public function test_show_builds_image_urls_from_rtrimmed_app_url_without_double_slash(): void
    {
        Config::set('app.url', 'https://app.example.com/');

        $admin = $this->admin();
        $user = User::factory()->create(['nro_doc' => '11222333']);
        $reviewer = User::factory()->create(['name' => 'Reviewer Admin']);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'front_image_path' => 'idv/front.jpg',
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/manual-identity-validations/'.$row->id)->assertOk()->json('data');

        $expectedBase = 'https://app.example.com';
        $this->assertSame(
            $expectedBase.'/api/admin/manual-identity-validations/'.$row->id.'/image/front',
            $data['front_image_url']
        );
        $this->assertNull($data['back_image_url']);
        $this->assertSame('Reviewer Admin', $data['reviewed_by_name']);
    }

    public function test_image_invalid_type_returns_json_not_found_with_exact_payload(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');

        $response = app(ManualIdentityValidationController::class)->image($row->id, 'invalid-type');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['error' => 'Invalid image type'], $response->getData(true));
    }

    public function test_image_returns_not_found_when_column_empty_or_file_missing(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'front_image_path' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/manual-identity-validations/'.$row->id.'/image/front')
            ->assertNotFound()
            ->assertJson(['error' => 'Image not found']);
    }

    public function test_image_streams_existing_file_with_inline_disposition(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $user = User::factory()->create();
        $relative = 'manual-idv/test-front.bin';
        Storage::disk('local')->put($relative, 'fake-image-bytes');
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'front_image_path' => $relative,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->get('api/admin/manual-identity-validations/'.$row->id.'/image/front');
        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline');
        $this->assertStringContainsString('fake-image-bytes', (string) $response->streamedContent());
    }

    public function test_review_rejects_unpaid_request_and_approves_paid_updates_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);

        $unpaid = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$unpaid->id.'/review', [
            'action' => 'approve',
        ])->assertUnprocessable()->assertJsonPath('error', 'Unpaid request cannot be reviewed');

        $paid = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->postJson('api/admin/manual-identity-validations/'.$paid->id.'/review', [
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('data.review_status', 'approved');

        $user->refresh();
        $this->assertTrue((bool) $user->identity_validated);
        $this->assertSame('manual', (string) $user->identity_validation_type);
    }

    public function test_review_approve_clears_prior_rejection_state(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'identity_validated' => false,
            'identity_validation_rejected_at' => now()->subDay(),
            'identity_validation_reject_reason' => 'dni_mismatch',
        ]);

        $rejectedManual = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now()->subDays(2),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'review_note' => 'Old rejection.',
        ]);

        MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'name_mismatch',
            'mp_payload' => ['first_name' => 'Jane'],
        ]);

        $paid = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$paid->id.'/review', [
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('data.review_status', 'approved');

        $user->refresh();
        $this->assertTrue((bool) $user->identity_validated);
        $this->assertNull($user->identity_validation_rejected_at);
        $this->assertNull($user->identity_validation_reject_reason);
        $this->assertDatabaseMissing('manual_identity_validations', ['id' => $rejectedManual->id]);
        $this->assertDatabaseHas('manual_identity_validations', ['id' => $paid->id, 'review_status' => 'approved']);
        $this->assertSame(0, MercadoPagoRejectedValidation::query()->where('user_id', $user->id)->count());
    }

    public function test_review_reject_clears_identity_on_user_row_in_database(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'identity_validated' => true,
            'identity_validated_at' => now(),
            'identity_validation_type' => 'manual',
        ]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'reject',
            'note' => 'Illegible documents.',
        ])->assertOk()->assertJsonPath('data.review_status', 'rejected');

        $fromDb = User::query()->findOrFail($user->id);
        $this->assertFalse((bool) $fromDb->identity_validated);
        $this->assertNull($fromDb->identity_validated_at);
        $this->assertNull($fromDb->identity_validation_type);
    }

    public function test_purge_deletes_stored_files_and_clears_paths(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $user = User::factory()->create();

        $front = 'idv/purge-front.jpg';
        Storage::disk('local')->put($front, 'x');
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'front_image_path' => $front,
            'back_image_path' => null,
            'selfie_image_path' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/purge')
            ->assertOk()
            ->assertJsonPath('message', 'Photos purged');

        $this->assertFalse(Storage::disk('local')->exists($front));
        $fresh = ManualIdentityValidation::query()->findOrFail($row->id);
        $this->assertNull($fresh->front_image_path);
    }

    public function test_show_reports_images_not_purged_when_paid_without_submitted_images(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/manual-identity-validations/'.$row->id)
            ->assertOk()
            ->assertJsonPath('data.has_images', false)
            ->assertJsonPath('data.images_purged_at', null);
    }

    public function test_purge_sets_images_purged_at(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $user = User::factory()->create();

        $front = 'idv/purge-front.jpg';
        Storage::disk('local')->put($front, 'x');
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'front_image_path' => $front,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/purge')
            ->assertOk();

        $fresh = ManualIdentityValidation::query()->findOrFail($row->id);
        $this->assertNotNull($fresh->images_purged_at);
    }

    public function test_show_reports_images_purged_after_admin_purge(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $user = User::factory()->create();

        $front = 'idv/purge-front.jpg';
        Storage::disk('local')->put($front, 'x');
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'front_image_path' => $front,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/purge')->assertOk();

        $this->getJson('api/admin/manual-identity-validations/'.$row->id)
            ->assertOk()
            ->assertJsonPath('data.has_images', false)
            ->assertJsonPath('data.images_purged_at', fn ($value) => $value !== null);
    }

    public function test_private_note_endpoint_updates_field_and_show_returns_it(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/private-note', [
            'private_admin_note' => 'Internal follow-up next week.',
        ])->assertOk()->assertJsonPath('data.private_admin_note', 'Internal follow-up next week.');

        $show = $this->getJson('api/admin/manual-identity-validations/'.$row->id)->assertOk();
        $this->assertSame('Internal follow-up next week.', $show->json('data.private_admin_note'));
    }

    public function test_review_approve_notifies_user_with_approved_action(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(NotificationServices::class, function ($mock) use ($user) {
            $mock->shouldReceive('send')
                ->twice()
                ->withArgs(function ($notification, $recipient, $channel) use ($user) {
                    if (! $notification instanceof ManualIdentityValidationReviewNotification) {
                        return false;
                    }
                    if ($notification->getAttribute('action') !== 'approved') {
                        return false;
                    }
                    if (! $recipient instanceof User || (int) $recipient->id !== (int) $user->id) {
                        return false;
                    }

                    return in_array($channel, [
                        \STS\Services\Notifications\Channels\DatabaseChannel::class,
                        \STS\Services\Notifications\Channels\PushChannel::class,
                    ], true);
                });
        });

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'approve',
            'note' => 'Documentación correcta.',
        ])->assertOk();
    }

    public function test_review_reject_notifies_user_with_rejected_action(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(NotificationServices::class, function ($mock) use ($user) {
            $mock->shouldReceive('send')
                ->twice()
                ->withArgs(function ($notification, $recipient, $channel) use ($user) {
                    if (! $notification instanceof ManualIdentityValidationReviewNotification) {
                        return false;
                    }
                    if ($notification->getAttribute('action') !== 'rejected') {
                        return false;
                    }
                    if (! $recipient instanceof User || (int) $recipient->id !== (int) $user->id) {
                        return false;
                    }

                    return in_array($channel, [
                        \STS\Services\Notifications\Channels\DatabaseChannel::class,
                        \STS\Services\Notifications\Channels\PushChannel::class,
                    ], true);
                });
        });

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'reject',
            'note' => 'Fotos ilegibles.',
        ])->assertOk();
    }

    public function test_review_pending_does_not_notify_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'pending',
            'note' => 'Necesitamos mejor calidad.',
        ])->assertOk();
    }

    public function test_update_state_sets_paid_and_paid_at_when_marking_paid(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'paid_at' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'paid' => true,
        ])->assertOk()->assertJsonPath('data.paid', true);

        $fresh = ManualIdentityValidation::query()->findOrFail($row->id);
        $this->assertTrue($fresh->paid);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_update_state_reject_clears_user_identity(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'identity_validated' => true,
            'identity_validated_at' => now(),
            'identity_validation_type' => 'manual',
        ]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_APPROVED,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'review_status' => 'rejected',
        ])->assertOk()->assertJsonPath('data.review_status', 'rejected');

        $user->refresh();
        $this->assertFalse((bool) $user->identity_validated);
        $this->assertNull($user->identity_validated_at);
        $this->assertNull($user->identity_validation_type);
    }

    public function test_update_state_does_not_notify_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'review_status' => 'approved',
        ])->assertOk();
    }

    public function test_update_state_sets_review_status_and_syncs_user_on_approve(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'review_status' => 'approved',
        ])->assertOk()->assertJsonPath('data.review_status', 'approved');

        $user->refresh();
        $this->assertTrue((bool) $user->identity_validated);
        $this->assertSame('manual', (string) $user->identity_validation_type);
    }

    public function test_show_includes_support_tickets_count_for_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $adminCreator = User::factory()->create(['is_admin' => true]);
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'account_verification',
            'subject' => 'User opened ticket',
            'status' => 'Open',
            'priority' => 'high',
        ]);
        SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'contact',
            'subject' => 'Admin opened ticket',
            'status' => 'Open',
            'priority' => 'normal',
            'created_by' => $adminCreator->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/manual-identity-validations/'.$row->id)->assertOk()->json('data');

        $this->assertArrayHasKey('support_tickets_count', $data);
        $this->assertSame(2, $data['support_tickets_count']);
    }
}
