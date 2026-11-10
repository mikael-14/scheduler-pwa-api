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
            // no 'primary': it is set in the database only (as in the admin panel)
            'range' => 'required|boolean',
            'all_day' => 'required|boolean',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
            'description' => 'nullable|string|max:255',
            'min_time' => 'nullable|date_format:H:i',
            'max_time' => 'nullable|date_format:H:i',
            // shifts: left out = the slots stay as they are (SlotDefinitions)
            'uses_slots' => 'sometimes|boolean',
            'slots' => 'sometimes|array|max:500',
            'slots.*.id' => 'nullable|integer',
            'slots.*.day' => 'required|date_format:Y-m-d',
            'slots.*.from' => 'required|date_format:H:i',
            'slots.*.to' => 'required|date_format:H:i',
            'slots.*.capacity' => 'nullable|integer|min:1|max:1000',
            'slots.*.comment' => 'nullable|string|max:1000',
        ];
    }
}
