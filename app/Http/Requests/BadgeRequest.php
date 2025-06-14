<?php

namespace STS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BadgeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $badgeId = $this->route('badge')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('badges')->ignore($badgeId),
            ],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'rules' => ['required', 'array'],
            'rules.type' => ['required', 'string', Rule::in([
                'registration_duration',
                'donated_to_campaign',
                'total_donated',
                'monthly_donor'
            ])],
            'rules.days' => ['required_if:rules.type,registration_duration', 'integer', 'min:1'],
            'rules.campaign_id' => ['required_if:rules.type,donated_to_campaign', 'integer', 'exists:campaigns,id'],
            'rules.amount' => ['required_if:rules.type,total_donated', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rules.type.in' => 'The badge type must be one of: registration_duration, donated_to_campaign, total_donated, monthly_donor',
            'rules.days.required_if' => 'The days field is required for registration duration badges',
            'rules.campaign_id.required_if' => 'The campaign ID is required for campaign donation badges',
            'rules.amount.required_if' => 'The amount is required for total donation badges',
        ];
    }
}
