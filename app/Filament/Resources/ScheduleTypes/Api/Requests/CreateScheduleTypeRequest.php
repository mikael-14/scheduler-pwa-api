<?php

namespace App\Filament\Resources\ScheduleTypes\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateScheduleTypeRequest extends FormRequest
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
			'name' => 'required',
			'color' => 'required',
			'status' => 'required',
			'range' => 'required',
			'all_day' => 'required',
			'start' => 'required',
			'end' => 'required',
			'description' => 'required',
			'min_time' => 'required',
			'max_time' => 'required'
		];
    }
}
