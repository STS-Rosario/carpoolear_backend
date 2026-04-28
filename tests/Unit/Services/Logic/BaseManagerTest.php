<?php

namespace Tests\Unit\Services\Logic;

use STS\Services\Logic\BaseManager;
use Tests\TestCase;

class BaseManagerTest extends TestCase
{
    public function test_get_errors_is_null_by_default(): void
    {
        $manager = new BaseManager;

        $this->assertNull($manager->getErrors());
    }

    public function test_set_errors_persists_array_payload(): void
    {
        $manager = new BaseManager;
        $errors = ['error' => 'invalid_input', 'fields' => ['email' => ['required']]];

        $manager->setErrors($errors);

        $this->assertSame($errors, $manager->getErrors());
    }

    public function test_set_errors_overwrites_previous_value(): void
    {
        $manager = new BaseManager;
        $manager->setErrors(['error' => 'first']);

        $manager->setErrors(['error' => 'second']);

        $this->assertSame(['error' => 'second'], $manager->getErrors());
    }
}
