<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'first_name' => ['required','string','max:255'],
            'last_name' => ['required','string','max:255'],
            'email' => ['required','email','unique:users,email'],
            'role_id' => ['required','exists:roles,id'],
            'hourly_rate' => ['nullable','numeric','min:0'],
            'location_ids' => ['nullable','array'],
            'location_ids.*' => ['integer','exists:locations,id'],
            'expand_descendants' => ['sometimes','boolean'],
            'password' => ['nullable','string','min:8','max:72'],
            'password_confirmation' => ['required_with:password','same:password'],
        ];
    }
}


