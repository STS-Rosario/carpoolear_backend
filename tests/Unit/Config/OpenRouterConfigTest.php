<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class OpenRouterConfigTest extends TestCase
{
    public function test_openrouter_defaults_point_to_the_public_api(): void
    {
        $this->assertSame('https://openrouter.ai/api/v1', config('services.openrouter.base_url'));
        $this->assertEmpty(config('services.openrouter.api_key'));
        $this->assertSame(30, config('services.openrouter.timeout'));
    }

    public function test_contribution_check_defaults_to_deepseek_flash_without_reasoning(): void
    {
        $this->assertSame(
            'deepseek/deepseek-v4.1-flash',
            config('services.openrouter.contribution_check.model')
        );
        $this->assertSame('off', config('services.openrouter.contribution_check.reasoning'));
    }

    public function test_env_example_documents_openrouter_variables(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        foreach ([
            'OPENROUTER_API_KEY=',
            'OPENROUTER_BASE_URL=',
            'OPENROUTER_TIMEOUT=',
            'OPENROUTER_CONTRIBUTION_CHECK_MODEL=',
            'OPENROUTER_CONTRIBUTION_CHECK_REASONING=',
        ] as $variable) {
            $this->assertStringContainsString($variable, $envExample);
        }
    }
}
