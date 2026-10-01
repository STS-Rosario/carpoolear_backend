<?php

namespace STS\Services\ContributionCheck;

/**
 * Turns the model's message content into a validated result. The model is
 * asked for strict JSON, but answers may still come wrapped in code fences or
 * prose, so the first JSON object is extracted and every field type-checked.
 */
class ContributionCheckResponseParser
{
    public function parse(string $content): ContributionCheckResult
    {
        $decoded = json_decode($this->extractJsonObject($content), true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidContributionCheckResponseException('Contribution check answer is not a JSON object.');
        }

        foreach (['suspected_contribution', 'exceeds_max', 'phone_in_description'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new InvalidContributionCheckResponseException("Contribution check answer is missing \"{$key}\".");
            }
        }

        if (! is_bool($decoded['exceeds_max']) || ! is_bool($decoded['phone_in_description'])) {
            throw new InvalidContributionCheckResponseException('Contribution check flags must be booleans.');
        }

        return new ContributionCheckResult(
            $this->parseAmount($decoded['suspected_contribution']),
            $decoded['exceeds_max'],
            $decoded['phone_in_description'],
        );
    }

    private function extractJsonObject(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $content) ?? $content;

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end < $start) {
            return $content;
        }

        return substr($content, $start, $end - $start + 1);
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if ((! is_int($value) && ! is_float($value) && ! is_string($value)) || ! is_numeric($value)) {
            throw new InvalidContributionCheckResponseException('suspected_contribution must be a number or null.');
        }

        $amount = (float) $value;

        if ($amount < 0) {
            throw new InvalidContributionCheckResponseException('suspected_contribution cannot be negative.');
        }

        return $amount;
    }
}
