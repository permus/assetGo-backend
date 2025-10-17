<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        $id = $this->route('id') ?? $this->route('team');
        return [
            'first_name' => ['sometimes','required','string','max:255'],
            'last_name' => ['sometimes','required','string','max:255'],
            'email' => ['sometimes','required','email','unique:users,email,'.$id],
            'role_id' => ['sometimes','required','exists:roles,id'],
            'hourly_rate' => ['nullable','numeric','min:0'],
            'location_ids' => ['nullable','array'],
            'location_ids.*' => ['integer','exists:locations,id'],
            'expand_descendants' => ['sometimes','boolean'],
        ];
    }

    /**
     * Add cross-company validation
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (!$user) {
                return;
            }

            // Validate role belongs to the same company
            if ($this->filled('role_id')) {
                $role = \App\Models\Role::find($this->role_id);
                if ($role && $role->company_id !== $user->company_id) {
                    $validator->errors()->add('role_id', 'The selected role does not belong to your company.');
                }
            }

            // Validate locations belong to the same company
            if ($this->filled('location_ids') && is_array($this->location_ids)) {
                $invalidLocations = \App\Models\Location::whereIn('id', $this->location_ids)
                    ->where('company_id', '!=', $user->company_id)
                    ->count();

                if ($invalidLocations > 0) {
                    $validator->errors()->add('location_ids', 'One or more selected locations do not belong to your company.');
                }
            }
        });
    }
}


