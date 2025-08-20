<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

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
}


