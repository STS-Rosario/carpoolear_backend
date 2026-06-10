<?php

namespace STS\Services\Argautos;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ArgautosClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
        private readonly int $requestDelayMs = 21000,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchBrands(): array
    {
        return $this->fetchPaginated($this->baseUrl.'/brands');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchModelsForBrand(int $argautosBrandId): array
    {
        if ($this->requestDelayMs > 0) {
            usleep($this->requestDelayMs * 1000);
        }

        return $this->fetchPaginated($this->baseUrl.'/brands/'.$argautosBrandId.'/models');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPaginated(string $url): array
    {
        $items = [];
        $nextUrl = $url;

        while ($nextUrl) {
            $response = $this->request()->get($nextUrl);

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 60);
                sleep(max(1, $retryAfter));

                continue;
            }

            $response->throw();
            $payload = $response->json();
            $items = array_merge($items, $payload['data'] ?? []);
            $nextUrl = $payload['links']['next'] ?? null;
        }

        return $items;
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()->timeout(30);

        if ($this->apiKey) {
            $request = $request->withToken($this->apiKey);
        }

        return $request;
    }
}
