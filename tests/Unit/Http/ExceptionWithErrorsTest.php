<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use STS\Http\ExceptionWithErrors;
use Tests\TestCase;

class ExceptionWithErrorsTest extends TestCase
{
    public function test_render_without_errors_returns_message_only(): void
    {
        $exception = new ExceptionWithErrors('User not found.');
        $response = $exception->render(Request::create('/', 'GET'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['message' => 'User not found.'], $response->getData(true));
        $this->assertArrayNotHasKey('errors', $response->getData(true));
    }

    public function test_render_with_array_errors_includes_errors_key(): void
    {
        $errors = ['email' => ['Invalid address']];
        $exception = new ExceptionWithErrors('Validation failed', $errors);
        $response = $exception->render(Request::create('/', 'POST'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Validation failed', $response->getData(true)['message']);
        $this->assertSame($errors, $response->getData(true)['errors']);
    }

    public function test_render_with_message_bag_object_serializes_errors(): void
    {
        $bag = new MessageBag(['field' => ['First error']]);
        $exception = new ExceptionWithErrors('Bag message', $bag);
        $response = $exception->render(Request::create('/', 'GET'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Bag message', $response->getData(true)['message']);
        $this->assertSame(['field' => ['First error']], $response->getData(true)['errors']);
    }

    public function test_render_with_empty_array_errors_preserves_errors_key(): void
    {
        $exception = new ExceptionWithErrors('No field errors', []);
        $response = $exception->render(Request::create('/', 'GET'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([], $response->getData(true)['errors']);
        $this->assertSame('No field errors', $response->getData(true)['message']);
    }

    public function test_render_with_custom_errors_object_uses_to_array_payload(): void
    {
        $errorsObject = new class
        {
            public function toArray(): array
            {
                return ['profile' => ['Invalid profile image']];
            }
        };

        $exception = new ExceptionWithErrors('Object errors', $errorsObject);
        $response = $exception->render(Request::create('/', 'POST'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Object errors', $response->getData(true)['message']);
        $this->assertSame(['profile' => ['Invalid profile image']], $response->getData(true)['errors']);
    }

    public function test_report_logs_exception_message(): void
    {
        Log::shouldReceive('info')->once()->with('Log me');

        $exception = new ExceptionWithErrors('Log me');
        $exception->report();
    }
}
