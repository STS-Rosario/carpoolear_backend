<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Http\Controllers\Api\v1\UserController;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\AnonymizationService;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use STS\Services\UserDeletionService;
use STS\Services\UserEditablePropertiesService;
use Tests\TestCase;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserControllerMutationSurvivorsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Mutant ID: Line 57:ContinueToBreak — skipping a falsy slot must use {@see continue}, not {@see break},
     * or the second uploaded file would never reach {@see UsersManager::uploadDoc}.
     */
    public function test_create_skips_falsy_driver_file_slot_then_uploads_remaining_files(): void
    {
        config(['carpoolear.module_validated_drivers' => true]);

        $good = UploadedFile::fake()->image('second.jpg', 40, 40);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('all')->andReturn([
            'name' => 'Driver Multi',
            'email' => 'driver-multi-'.uniqid('', true).'@example.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
        ]);
        $request->shouldReceive('file')->with('driver_data_docs')->andReturn([null, $good]);

        $userLogic = Mockery::mock(UsersManager::class);
        $userLogic->shouldReceive('uploadDoc')
            ->once()
            ->with(Mockery::on(fn (UploadedFile $f) => $f === $good))
            ->andReturn('uploaded-ref-1');
        $userLogic->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                if (! isset($data['driver_data_docs'])) {
                    return false;
                }
                $decoded = json_decode($data['driver_data_docs'], true);

                return is_array($decoded) && $decoded === ['uploaded-ref-1'];
            }))
            ->andReturnUsing(fn () => User::factory()->create(['active' => false, 'banned' => false]));

        $controller = new UserController(
            $userLogic,
            Mockery::mock(DeviceManager::class),
            Mockery::mock(UserDeletionService::class),
            Mockery::mock(AnonymizationService::class),
            Mockery::mock(UserEditablePropertiesService::class),
        );

        $response = $controller->create($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Mutant IDs: Line 88:RemoveMethodCall, Line 88:ConcatRemoveLeft, Line 88:ConcatRemoveRight, Line 88:ConcatSwitchSides.
     */
    public function test_registration_response_payload_logs_full_message_when_jwt_issue_fails_for_active_user(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $jwt = Mockery::mock(\Tymon\JWTAuth\JWTAuth::class);
        $jwt->shouldIgnoreMissing();
        $jwt->shouldReceive('fromUser')
            ->once()
            ->with(Mockery::on(fn (User $u) => $u->is($user)))
            ->andThrow(new JWTException('fixture-jwt-failure'));
        $this->app->instance('tymon.jwt.auth', $jwt);

        $controller = app(UserController::class);
        $method = new \ReflectionMethod(UserController::class, 'registrationResponsePayload');
        $method->setAccessible(true);
        $payload = $method->invoke($controller, $user);

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('token', $payload);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Could not issue JWT after registration: fixture-jwt-failure');
    }

    /**
     * Mutant IDs: Line 396:RemoveMethodCall, Line 396:ConcatRemoveLeft, Line 396:ConcatRemoveRight, Line 396:ConcatSwitchSides.
     */
    public function test_delete_account_delete_branch_logs_when_jwt_invalidate_throws(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $tokenHandle = new \stdClass;

        $jwt = Mockery::mock(\Tymon\JWTAuth\JWTAuth::class);
        $jwt->shouldReceive('getToken')->once()->andReturn($tokenHandle);
        $jwt->shouldReceive('invalidate')
            ->once()
            ->with($tokenHandle)
            ->andThrow(new \Exception('invalidate-delete-unit'));
        $this->app->instance('tymon.jwt.auth', $jwt);

        $device = Mockery::mock(DeviceManager::class);
        $device->shouldReceive('logoutAllDevices')->once()->with($user);

        $deletion = Mockery::mock(UserDeletionService::class);
        $deletion->shouldReceive('deleteUser')->once()->with($user)->andReturn(true);

        $controller = new UserController(
            Mockery::mock(UsersManager::class),
            $device,
            $deletion,
            Mockery::mock(AnonymizationService::class),
            Mockery::mock(UserEditablePropertiesService::class),
        );

        $response = $controller->deleteAccount(Request::create('/api/users/delete-account', 'POST'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('deleted', json_decode($response->getContent(), true)['action'] ?? null);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to invalidate JWT after delete: invalidate-delete-unit');
    }

    /**
     * Mutant IDs: Line 416:RemoveMethodCall, Line 416:ConcatRemoveLeft, Line 416:ConcatRemoveRight, Line 416:ConcatSwitchSides.
     */
    public function test_delete_account_anonymize_branch_logs_when_jwt_invalidate_throws(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);
        Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'api');

        $tokenHandle = new \stdClass;

        $jwt = Mockery::mock(\Tymon\JWTAuth\JWTAuth::class);
        $jwt->shouldReceive('getToken')->once()->andReturn($tokenHandle);
        $jwt->shouldReceive('invalidate')
            ->once()
            ->with($tokenHandle)
            ->andThrow(new \Exception('invalidate-anon-unit'));
        $this->app->instance('tymon.jwt.auth', $jwt);

        $device = Mockery::mock(DeviceManager::class);
        $device->shouldReceive('logoutAllDevices')->once()->with($user);

        $anon = Mockery::mock(AnonymizationService::class);
        $anon->shouldReceive('anonymize')->once()->with($user)->andReturn($user);

        $controller = new UserController(
            Mockery::mock(UsersManager::class),
            $device,
            Mockery::mock(UserDeletionService::class),
            $anon,
            Mockery::mock(UserEditablePropertiesService::class),
        );

        $response = $controller->deleteAccount(Request::create('/api/users/delete-account', 'POST'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('anonymized', json_decode($response->getContent(), true)['action'] ?? null);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to invalidate JWT after anonymize: invalidate-anon-unit');
    }
}
