<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class TransferAssetRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic as needed
    }

    public function rules()
    {
        return [
            'to_location_id' => 'required|exists:locations,id',
            'to_user_id' => 'nullable|exists:users,id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'condition_report' => 'nullable|string|max:1000',
        ];
    }
} 