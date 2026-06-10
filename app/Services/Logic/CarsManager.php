<?php

namespace STS\Services\Logic;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use STS\Models\Car as CarModel;
use STS\Models\CarModel as CatalogCarModel;
use STS\Models\User as UserModel;
use STS\Repository\CarsRepository;

class CarsManager extends BaseManager
{
    protected $repo;

    public function __construct(CarsRepository $carsRepo)
    {
        $this->repo = $carsRepo;
    }

    public function validator(array $data, $userId = null, $carId = null, bool $isCreate = false)
    {
        $rules = [
            'patente' => ['required', 'string', 'max:10'],
            'description' => 'nullable|string|max:255',
            'car_brand_id' => ['nullable', 'integer', Rule::exists('car_brands', 'id')->where('is_active', true)],
            'car_model_id' => ['nullable', 'integer', Rule::exists('car_models', 'id')->where('is_active', true)],
            'brand_other' => ['nullable', 'string', 'max:100'],
            'model_other' => ['nullable', 'string', 'max:100'],
            'car_color_id' => ['nullable', 'integer', Rule::exists('car_colors', 'id')->where('is_active', true)],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(int) date('Y')],
        ];

        if ($isCreate) {
            $rules['car_color_id'][] = 'required';
            $rules['year'][] = 'required';
        }

        if ($userId) {
            $uniquePatente = Rule::unique('cars', 'patente')
                ->where(fn ($query) => $query->where('user_id', $userId)->whereNull('deleted_at'));

            if ($carId) {
                $uniquePatente->ignore($carId);
            }

            $rules['patente'][] = $uniquePatente;
        }

        $validator = Validator::make($data, $rules);
        $validator->after(function ($v) use ($data, $isCreate) {
            $this->validateCatalogFields($v, $data, $isCreate);
        });

        return $validator;
    }

    public function create(UserModel $user, $data)
    {
        $v = $this->validator($data, $user->id, null, true);
        if ($v->fails()) {
            $this->setErrors($v->errors());

            return;
        }

        $payload = $this->normalizePayload($data);

        $existing = $this->repo->findByUserAndPatenteIncludingTrashed(
            $user->id,
            $payload['patente']
        );

        if ($existing && $existing->trashed()) {
            $existing->restore();
            $existing->fill($payload);
            $this->repo->update($existing);

            return $existing->fresh(['brand', 'carModel', 'color']);
        }

        $car = new CarModel;
        $car->fill($payload);
        $car->user_id = $user->id;
        $this->repo->create($car);

        return $car->fresh(['brand', 'carModel', 'color']);
    }

    public function update(UserModel $user, $id, $data)
    {
        $car = $this->show($user, $id);
        if ($car) {
            $v = $this->validator($data, $user->id, $id, false);
            if ($v->fails()) {
                $this->setErrors($v->errors());

                return;
            }

            $car->fill($this->normalizePayload($data));
            $this->repo->update($car);

            return $car->fresh(['brand', 'carModel', 'color']);
        }

        $this->setErrors(['error' => 'car_not_found']);

    }

    public function show(UserModel $user, $id)
    {
        $car = $this->repo->show($id);
        if ($car && $car->user_id == $user->id) {
            return $car->load(['brand', 'carModel', 'color']);
        }

        $this->setErrors(['error' => 'car_not_found']);

    }

    public function delete(UserModel $user, $id)
    {
        $car = $this->show($user, $id);
        if ($car) {
            if ($this->repo->delete($car)) {
                return true;
            }

            $this->setErrors(['error' => 'can_delete_car']);

            return;
        }

        $this->setErrors(['error' => 'car_not_found']);

    }

    public function index(UserModel $user)
    {
        return $this->repo->index($user)->load(['brand', 'carModel', 'color']);
    }

    /**
     * @param  \Illuminate\Validation\Validator  $validator
     */
    private function validateCatalogFields($validator, array $data, bool $isCreate): void
    {
        $hasBrandId = ! empty($data['car_brand_id']);
        $hasModelId = ! empty($data['car_model_id']);
        $hasBrandOther = $this->hasValue($data['brand_other'] ?? null);
        $hasModelOther = $this->hasValue($data['model_other'] ?? null);

        if ($hasBrandId && $hasBrandOther) {
            $validator->errors()->add('brand_other', 'Cannot set both car_brand_id and brand_other.');
        }

        if ($hasModelId && $hasModelOther) {
            $validator->errors()->add('model_other', 'Cannot set both car_model_id and model_other.');
        }

        $hasCatalogIds = $hasBrandId && $hasModelId;
        $hasCatalogOther = $hasBrandOther && $hasModelOther;
        $hasCatalogBrandWithOtherModel = $hasBrandId && $hasModelOther && ! $hasModelId;

        if ($isCreate && ! $hasCatalogIds && ! $hasCatalogOther && ! $hasCatalogBrandWithOtherModel) {
            $validator->errors()->add('car_brand_id', 'A brand and model or custom values are required.');
        }

        if ($hasBrandId && $hasModelId) {
            $model = CatalogCarModel::query()->find($data['car_model_id']);
            if (! $model || (int) $model->car_brand_id !== (int) $data['car_brand_id']) {
                $validator->errors()->add('car_model_id', 'The selected model does not belong to the selected brand.');
            }
        }
    }

    private function normalizePayload(array $data): array
    {
        $payload = [
            'patente' => $data['patente'],
            'description' => $data['description'] ?? '',
            'car_brand_id' => $data['car_brand_id'] ?? null,
            'car_model_id' => $data['car_model_id'] ?? null,
            'brand_other' => $this->hasValue($data['brand_other'] ?? null) ? trim($data['brand_other']) : null,
            'model_other' => $this->hasValue($data['model_other'] ?? null) ? trim($data['model_other']) : null,
            'car_color_id' => $data['car_color_id'] ?? null,
            'year' => isset($data['year']) ? (int) $data['year'] : null,
        ];

        if ($payload['car_brand_id']) {
            $payload['brand_other'] = null;
        } else {
            $payload['car_brand_id'] = null;
            $payload['car_model_id'] = null;
        }

        if ($payload['car_model_id']) {
            $payload['model_other'] = null;
        } elseif ($this->hasValue($payload['model_other'] ?? null)) {
            $payload['car_model_id'] = null;
        } else {
            $payload['model_other'] = null;
        }

        return $payload;
    }

    private function hasValue($value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
