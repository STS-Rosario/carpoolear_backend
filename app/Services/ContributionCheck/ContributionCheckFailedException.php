<?php

namespace STS\Services\ContributionCheck;

use RuntimeException;

/**
 * The LLM contribution check could not produce a result (HTTP error, timeout,
 * unusable answer). Thrown so the queued job can retry with backoff.
 */
class ContributionCheckFailedException extends RuntimeException {}
