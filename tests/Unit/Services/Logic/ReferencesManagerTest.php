<?php

namespace Tests\Unit\Services\Logic;

use STS\Models\References;
use STS\Models\User;
use STS\Repository\ReferencesRepository;
use STS\Services\Logic\ReferencesManager;
use Tests\TestCase;

class ReferencesManagerTest extends TestCase
{
    private function manager(): ReferencesManager
    {
        return new ReferencesManager(new ReferencesRepository);
    }

    public function test_validator_requires_comment_and_user_id_to(): void
    {
        $v = $this->manager()->validator([]);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('comment'));
        $this->assertTrue($v->errors()->has('user_id_to'));
    }

    public function test_validator_fails_when_comment_exceeds_max_length(): void
    {
        $v = $this->manager()->validator([
            'comment' => str_repeat('a', 261),
            'user_id_to' => 123,
        ]);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('comment'));
    }

    public function test_validator_accepts_comment_with_exact_max_length(): void
    {
        $v = $this->manager()->validator([
            'comment' => str_repeat('a', 260),
            'user_id_to' => 123,
        ]);

        $this->assertFalse($v->fails());
    }

    public function test_validator_fails_with_whitespace_only_comment_string(): void
    {
        $v = $this->manager()->validator([
            'comment' => '   ',
            'user_id_to' => 123,
        ]);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('comment'));
    }

    public function test_create_returns_null_and_sets_errors_when_validation_fails(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->create($user, ['comment' => '', 'user_id_to' => null]);

        $this->assertNull($result);
        $this->assertNotNull($manager->getErrors());
    }

    public function test_create_returns_null_when_comment_is_not_string(): void
    {
        $user = User::factory()->create();
        $to = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->create($user, [
            'comment' => ['not-a-string'],
            'user_id_to' => $to->id,
        ]);

        $this->assertNull($result);
        $this->assertNotNull($manager->getErrors());
    }

    public function test_create_fails_when_target_user_does_not_exist(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->create($user, [
            'comment' => 'Great driver.',
            'user_id_to' => 999_999_999,
        ]);

        $this->assertNull($result);
        $errors = $manager->getErrors();
        $this->assertIsArray($errors);
        $this->assertSame('user_doesnt_exist', $errors['error']);
    }

    public function test_create_fails_when_target_is_same_as_author(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->create($user, [
            'comment' => 'Self reference',
            'user_id_to' => $user->id,
        ]);

        $this->assertNull($result);
        $this->assertSame('reference_same_user', $manager->getErrors()['error']);
    }

    public function test_create_fails_when_reference_already_exists_between_users(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        References::create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Existing',
        ]);

        $manager = $this->manager();
        $result = $manager->create($from, [
            'comment' => 'Another comment',
            'user_id_to' => $to->id,
        ]);

        $this->assertNull($result);
        $this->assertSame('reference_exist', $manager->getErrors()['error']);
    }

    public function test_create_persists_reference_and_returns_model(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $manager = $this->manager();

        $reference = $manager->create($from, [
            'comment' => 'Punctual and safe.',
            'user_id_to' => $to->id,
        ]);

        $this->assertInstanceOf(References::class, $reference);
        $this->assertNotNull($reference->id);
        $this->assertDatabaseHas('users_references', [
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Punctual and safe.',
        ]);
    }

    public function test_create_allows_reference_in_reverse_direction(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        References::create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Forward',
        ]);

        $manager = $this->manager();
        $reverse = $manager->create($to, [
            'comment' => 'Reverse',
            'user_id_to' => $from->id,
        ]);

        $this->assertInstanceOf(References::class, $reverse);
        $this->assertDatabaseHas('users_references', [
            'user_id_from' => $to->id,
            'user_id_to' => $from->id,
            'comment' => 'Reverse',
        ]);
    }
}
