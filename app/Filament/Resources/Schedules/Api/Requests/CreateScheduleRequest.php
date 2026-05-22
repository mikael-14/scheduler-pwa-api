<?php

namespace App\Filament\Resources\Schedules\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateScheduleRequest extends FormRequest
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
			'start' => 'required',
			'end' => 'required',
			'all_day' => 'required',
			'description' => 'required',
			'internal_note' => 'required',
			'status' => 'required',
			'user_id' => 'required',
			'schedule_type_id' => 'required',
			'deleted_at' => 'required'
		];
    }
}
