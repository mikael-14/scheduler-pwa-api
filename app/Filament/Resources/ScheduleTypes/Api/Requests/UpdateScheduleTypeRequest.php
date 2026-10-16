<?php

namespace App\Filament\Resources\ScheduleTypes\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
                return [
            'name' => 'required|string|max:50',
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'status' => 'required|boolean',
            'primary' => 'sometimes|boolean',
            'range' => 'required|boolean',
            'all_day' => 'required|boolean',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
            'description' => 'nullable|string|max:255',
            'min_time' => 'nullable|date_format:H:i',
            'max_time' => 'nullable|date_format:H:i',
        ];
    }
}
