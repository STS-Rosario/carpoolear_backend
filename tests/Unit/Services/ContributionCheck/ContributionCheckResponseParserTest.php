<?php

namespace Tests\Unit\Services\ContributionCheck;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use STS\Services\ContributionCheck\ContributionCheckResponseParser;
use STS\Services\ContributionCheck\InvalidContributionCheckResponseException;

class ContributionCheckResponseParserTest extends TestCase
{
    private function parser(): ContributionCheckResponseParser
    {
        return new ContributionCheckResponseParser;
    }

    public function test_parses_strict_json_object(): void
    {
        $result = $this->parser()->parse(
            '{"suspected_contribution": 24000, "exceeds_max": true, "phone_in_description": false}'
        );

        $this->assertSame(24000.0, $result->suspectedContribution);
        $this->assertTrue($result->exceedsMax);
        $this->assertFalse($result->phoneInDescription);
    }

    public function test_accepts_null_suspected_contribution(): void
    {
        $result = $this->parser()->parse(
            '{"suspected_contribution": null, "exceeds_max": false, "phone_in_description": true}'
        );

        $this->assertNull($result->suspectedContribution);
        $this->assertFalse($result->exceedsMax);
        $this->assertTrue($result->phoneInDescription);
    }

    public function test_strips_markdown_code_fences(): void
    {
        $result = $this->parser()->parse(
            "```json\n{\"suspected_contribution\": 18500.5, \"exceeds_max\": true, \"phone_in_description\": true}\n```"
        );

        $this->assertSame(18500.5, $result->suspectedContribution);
        $this->assertTrue($result->exceedsMax);
        $this->assertTrue($result->phoneInDescription);
    }

    public function test_extracts_the_json_object_from_surrounding_text(): void
    {
        $result = $this->parser()->parse(
            'Respuesta: {"suspected_contribution": null, "exceeds_max": false, "phone_in_description": false} Gracias.'
        );

        $this->assertFalse($result->exceedsMax);
    }

    public function test_accepts_numeric_string_amounts(): void
    {
        $result = $this->parser()->parse(
            '{"suspected_contribution": "24000", "exceeds_max": true, "phone_in_description": false}'
        );

        $this->assertSame(24000.0, $result->suspectedContribution);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidResponses(): array
    {
        return [
            'empty' => [''],
            'not json' => ['no lo sé'],
            'truncated json' => ['{"suspected_contribution": 24000, "exceeds_max": tr'],
            'json array' => ['[true, false]'],
            'missing exceeds_max' => ['{"suspected_contribution": null, "phone_in_description": false}'],
            'missing phone flag' => ['{"suspected_contribution": null, "exceeds_max": false}'],
            'missing suspected_contribution' => ['{"exceeds_max": false, "phone_in_description": false}'],
            'string boolean' => ['{"suspected_contribution": null, "exceeds_max": "true", "phone_in_description": false}'],
            'numeric boolean' => ['{"suspected_contribution": null, "exceeds_max": false, "phone_in_description": 1}'],
            'non numeric amount' => ['{"suspected_contribution": "mucho", "exceeds_max": true, "phone_in_description": false}'],
            'negative amount' => ['{"suspected_contribution": -5, "exceeds_max": false, "phone_in_description": false}'],
        ];
    }

    #[DataProvider('invalidResponses')]
    public function test_rejects_invalid_responses(string $content): void
    {
        $this->expectException(InvalidContributionCheckResponseException::class);

        $this->parser()->parse($content);
    }
}
