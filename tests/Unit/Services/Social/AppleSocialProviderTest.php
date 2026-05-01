<?php

namespace Tests\Unit\Services\Social;

use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Services\Social\AppleSocialProvider;
use Tests\TestCase;

class AppleSocialProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_user_data_logs_single_concatenated_string_matching_get_user_data_plus_json(): void
    {
        $data = [
            'user' => 'apple-sub',
            'email' => 'a@icloud.test',
            'fullName' => [
                'givenName' => 'Ada',
                'familyName' => 'Lovelace',
            ],
        ];

        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::on(function (mixed $message) use ($data): bool {
                return is_string($message) && $message === 'getUserData'.json_encode($data);
            }));

        $row = (new AppleSocialProvider('ignored'))->getUserData($data);

        $this->assertSame('apple-sub', $row['provider_user_id']);
        $this->assertSame('a@icloud.test', $row['email']);
        $this->assertSame('Ada Lovelace', $row['name']);
        $this->assertNull($row['gender']);
        $this->assertFalse($row['terms_and_conditions']);
    }

    public function test_get_user_data_default_name_when_no_full_name(): void
    {
        Log::shouldReceive('info')->once();

        $row = (new AppleSocialProvider('x'))->getUserData([
            'user' => 'u1',
            'email' => null,
        ]);

        $this->assertSame('Apple ID Anónimo', $row['name']);
        $this->assertNull($row['email']);
    }

    public function test_get_user_data_given_name_only_then_family_appended(): void
    {
        Log::shouldReceive('info')->twice();

        $onlyGiven = (new AppleSocialProvider('x'))->getUserData([
            'user' => 'u2',
            'fullName' => ['givenName' => 'Pat'],
        ]);
        $this->assertSame('Pat', $onlyGiven['name']);

        $onlyFamily = (new AppleSocialProvider('x'))->getUserData([
            'user' => 'u3',
            'fullName' => ['familyName' => 'Lee'],
        ]);
        $this->assertSame('Apple ID Anónimo Lee', $onlyFamily['name']);
    }
}
