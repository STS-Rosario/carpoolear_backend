<?php

namespace STS\Services\Argautos;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use STS\Models\CarBrand;
use STS\Models\CarModel;

class CarCatalogSyncService
{
    public const LOCK_KEY = 'car-catalog-sync';

    public const STATUS_CACHE_KEY = 'car-catalog-sync-status';

    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
        private readonly ?int $requestDelayMs = null,
    ) {}

    public function shouldImportModelName(string $name): bool
    {
        if (preg_match('/^\d+P\s/', $name)) {
            return false;
        }

        if (preg_match('/\b(TDCI|AT|CVT)\b/i', $name)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function sync(string $mode = 'incremental', bool $dryRun = false): array
    {
        $client = $this->client();
        $summary = [
            'mode' => $mode,
            'dry_run' => $dryRun,
            'brands_created' => 0,
            'brands_updated' => 0,
            'models_created' => 0,
            'models_skipped' => 0,
            'errors' => 0,
        ];

        $brands = $client->fetchBrands();

        foreach ($brands as $brandData) {
            $brand = $this->upsertBrand($brandData, $mode, $dryRun, $summary);
            if (! $brand && ! $dryRun) {
                $brand = CarBrand::query()->where('argautos_id', $brandData['id'])->first();
            }

            if (! $brand && $dryRun) {
                continue;
            }

            if (! $brand) {
                $summary['errors']++;

                continue;
            }

            $models = $client->fetchModelsForBrand((int) $brandData['id']);

            foreach ($models as $modelData) {
                if (! $this->shouldImportModelName((string) ($modelData['name'] ?? ''))) {
                    $summary['models_skipped']++;

                    continue;
                }

                $this->upsertModel($brand, $modelData, $mode, $dryRun, $summary);
            }
        }

        return $summary;
    }

    public function storeStatus(array $summary, bool $running = false, ?string $error = null): void
    {
        Cache::put(self::STATUS_CACHE_KEY, [
            'running' => $running,
            'last_run' => array_merge($summary, [
                'finished_at' => now()->toIso8601String(),
                'error' => $error,
            ]),
        ], now()->addDays(7));
    }

    /**
     * @return array<string, mixed>
     */
    public function currentStatus(): array
    {
        return Cache::get(self::STATUS_CACHE_KEY, [
            'running' => false,
            'last_run' => null,
        ]);
    }

    private function client(): ArgautosClient
    {
        return new ArgautosClient(
            rtrim($this->baseUrl ?? (string) config('carpoolear.argautos_api_base_url'), '/'),
            $this->apiKey ?? config('carpoolear.argautos_api_key'),
            $this->requestDelayMs ?? (int) config('carpoolear.argautos_request_delay_ms', 21000),
        );
    }

    /**
     * @param  array<string, mixed>  $brandData
     * @param  array<string, int|string|null>  $summary
     */
    private function upsertBrand(array $brandData, string $mode, bool $dryRun, array &$summary): ?CarBrand
    {
        $existing = CarBrand::query()->where('argautos_id', $brandData['id'])->first();

        if ($existing) {
            if ($mode === 'initial' && ! $dryRun) {
                $existing->fill([
                    'name' => $brandData['name'],
                    'slug' => $brandData['slug'] ?? Str::slug((string) $brandData['name']),
                    'is_active' => true,
                ]);
                $existing->save();
                $summary['brands_updated']++;
            }

            return $existing;
        }

        if ($dryRun) {
            $summary['brands_created']++;

            return null;
        }

        $brand = CarBrand::create([
            'name' => $brandData['name'],
            'slug' => $this->uniqueBrandSlug($brandData['slug'] ?? Str::slug((string) $brandData['name'])),
            'argautos_id' => $brandData['id'],
            'is_active' => true,
        ]);
        $summary['brands_created']++;

        return $brand;
    }

    /**
     * @param  array<string, mixed>  $modelData
     * @param  array<string, int|string|null>  $summary
     */
    private function upsertModel(CarBrand $brand, array $modelData, string $mode, bool $dryRun, array &$summary): void
    {
        $existing = CarModel::query()
            ->where('car_brand_id', $brand->id)
            ->where('argautos_id', $modelData['id'])
            ->first();

        if ($existing) {
            if ($mode === 'initial' && ! $dryRun) {
                $existing->fill([
                    'name' => $modelData['name'],
                    'slug' => $modelData['slug'] ?? Str::slug((string) $modelData['name']),
                    'is_active' => true,
                ]);
                $existing->save();
            }

            return;
        }

        if ($dryRun) {
            $summary['models_created']++;

            return;
        }

        CarModel::create([
            'car_brand_id' => $brand->id,
            'name' => $modelData['name'],
            'slug' => $this->uniqueModelSlug($brand->id, $modelData['slug'] ?? Str::slug((string) $modelData['name'])),
            'argautos_id' => $modelData['id'],
            'is_active' => true,
        ]);
        $summary['models_created']++;
    }

    private function uniqueBrandSlug(string $slug): string
    {
        $base = $slug ?: 'brand';
        $candidate = $base;
        $suffix = 1;

        while (CarBrand::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueModelSlug(int $brandId, string $slug): string
    {
        $base = $slug ?: 'model';
        $candidate = $base;
        $suffix = 1;

        while (CarModel::query()->where('car_brand_id', $brandId)->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
