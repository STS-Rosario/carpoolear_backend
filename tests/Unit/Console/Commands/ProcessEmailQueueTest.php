<?php

namespace Tests\Unit\Console\Commands;

use Mockery;
use STS\Console\Commands\ProcessEmailQueue;
use Tests\TestCase;

class ProcessEmailQueueTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_invokes_queue_work_with_selected_options(): void
    {
        $command = Mockery::mock(ProcessEmailQueue::class)->makePartial();
        $command->shouldReceive('option')->once()->with('timeout')->andReturn(120);
        $command->shouldReceive('option')->once()->with('tries')->andReturn(7);
        $command->shouldReceive('option')->once()->with('sleep')->andReturn(9);

        $command->shouldReceive('info')->once()->with('Starting email queue processing...');
        $command->shouldReceive('call')
            ->once()
            ->with('queue:work', [
                'connection' => 'database',
                'queue' => 'emails',
                '--timeout' => 120,
                '--tries' => 7,
                '--sleep' => 9,
                '--verbose' => true,
            ]);
        $command->shouldReceive('info')->once()->with('Email queue processing completed.');

        $command->handle();
        $this->addToAssertionCount(1);
    }

    public function test_command_signature_and_description_include_email_queue_contract(): void
    {
        $command = new ProcessEmailQueue;

        $this->assertStringContainsString('queue:process-emails', $command->getName());
        $this->assertStringContainsString('Process the email queue', $command->getDescription());
        $this->assertSame('timeout', $command->getDefinition()->getOption('timeout')->getName());
        $this->assertSame(60, (int) $command->getDefinition()->getOption('timeout')->getDefault());
        $this->assertSame(3, (int) $command->getDefinition()->getOption('tries')->getDefault());
        $this->assertSame(3, (int) $command->getDefinition()->getOption('sleep')->getDefault());
    }
}
