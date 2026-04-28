<?php

namespace Tests\Unit\Console\Commands;

use STS\Console\Commands\UpdateUser;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\References;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    public function test_handle_reassigns_related_records_to_new_user(): void
    {
        $original = User::factory()->create(['active' => true]);
        $new = User::factory()->create(['active' => true]);
        $other = User::factory()->create(['active' => true]);

        $trip = Trip::factory()->create(['user_id' => $original->id]);

        $ratingFrom = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $original->id,
            'user_id_to' => $other->id,
        ]);
        $ratingTo = Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $other->id,
            'user_id_to' => $original->id,
        ]);
        $passenger = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $original->id,
        ]);
        $referenceFrom = References::query()->create([
            'user_id_from' => $original->id,
            'user_id_to' => $other->id,
            'comment' => 'from original',
        ]);
        $referenceTo = References::query()->create([
            'user_id_from' => $other->id,
            'user_id_to' => $original->id,
            'comment' => 'to original',
        ]);

        $this->artisan('user:update', [
            'original' => $original->id,
            'new' => $new->id,
        ])
            ->expectsOutput('Trips, references ratings and passenger have been updated.')
            ->assertExitCode(0);

        $this->assertSame($new->id, (int) $ratingFrom->fresh()->user_id_from);
        $this->assertSame($new->id, (int) $ratingTo->fresh()->user_id_to);
        $this->assertSame($new->id, (int) $passenger->fresh()->user_id);
        $this->assertSame($new->id, (int) $trip->fresh()->user_id);
        $this->assertSame($new->id, (int) $referenceFrom->fresh()->user_id_from);
        $this->assertSame($new->id, (int) $referenceTo->fresh()->user_id_to);
    }

    public function test_handle_with_remove_deactivates_original_user_after_confirmation(): void
    {
        $original = User::factory()->create(['active' => true]);
        $new = User::factory()->create(['active' => true]);

        $this->artisan('user:update', [
            'original' => $original->id,
            'new' => $new->id,
            '--remove' => true,
        ])
            ->expectsConfirmation('Do you wish to continue? This will remove the user from the database [y|N]', 'yes')
            ->expectsOutput('User has been removed.')
            ->expectsOutput('Trips, references ratings and passenger have been updated.')
            ->assertExitCode(0);

        $this->assertSame(0, (int) $original->fresh()->active);
    }

    public function test_command_signature_and_description_match_expected_contract(): void
    {
        $command = new UpdateUser;

        $this->assertSame('user:update', $command->getName());
        $this->assertStringContainsString(
            'Update trips, ratings and passenger for duplicated users',
            $command->getDescription()
        );
        $this->assertTrue($command->getDefinition()->hasArgument('original'));
        $this->assertTrue($command->getDefinition()->hasArgument('new'));
        $this->assertTrue($command->getDefinition()->hasOption('remove'));
    }
}
