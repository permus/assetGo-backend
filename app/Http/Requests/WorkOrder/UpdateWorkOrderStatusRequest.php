<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateWorkOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statusId = $this->route('id');
        
        return [
            'name' => 'required|string|max:100',
            'company_id' => 'nullable|exists:companies,id',
            'sort' => 'nullable|integer|min:0',
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('work_order_status')->ignore($statusId),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->name),
            'company_id' => $this->company_id ?? auth()->user()->company_id,
            'is_management' => false, // Never user-settable
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'status name',
            'company_id' => 'company',
            'sort' => 'sort order',
            'slug' => 'status slug',
        ];
    }
}
