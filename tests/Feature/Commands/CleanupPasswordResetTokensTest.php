<?php

namespace Tests\Feature\Commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CleanupPasswordResetTokensTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('failed_jobs');
        parent::tearDown();
    }

    public function test_prints_failed_job_cleanup_line_only_when_matching_rows_deleted(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at');
        });

        DB::table('failed_jobs')->insert([
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'SendPasswordResetEmail', 'job' => 'STS\\Jobs\\SendPasswordResetEmail']),
            'exception' => 'test',
            'failed_at' => now()->subDays(2),
        ]);

        $this->artisan('auth:cleanup-reset-tokens', ['--hours' => 1])
            ->expectsOutputToContain('Also cleaned up 1 failed email jobs.')
            ->assertSuccessful();
    }

    public function test_does_not_print_failed_job_line_when_payload_does_not_match(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at');
        });

        DB::table('failed_jobs')->insert([
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'OtherJob', 'job' => 'Other']),
            'exception' => 'test',
            'failed_at' => now()->subDays(2),
        ]);

        $this->artisan('auth:cleanup-reset-tokens', ['--hours' => 1])
            ->doesntExpectOutputToContain('Also cleaned up')
            ->assertSuccessful();
    }
}
