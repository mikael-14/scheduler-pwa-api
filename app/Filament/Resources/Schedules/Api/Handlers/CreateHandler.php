<?php

namespace App\Filament\Resources\Schedules\Api\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Schedules\Api\Requests\CreateScheduleRequest;
use App\Models\ScheduleUser;

class CreateHandler extends Handlers
{
    public static string | null $uri = '/';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'Create:Schedule';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create Schedule
     *
     * @param CreateScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateScheduleRequest $request)
    {
        $request->validate($this->rules());

        return DB::transaction(function () use ($request) {
            $model = new (static::getModel());

            $model->fill($request->all());

            $model->save();

            if ($request->has('schedule_users')) {
                $model->schedule_users()->createMany($request->input('schedule_users'));
            }

            return static::sendSuccessResponse($model->load('schedule_users'), "Successfully Create Resource");
        });
    }
    public function rules(): array
    {
        $rules = static::getModel()::getValidationRules();
        $rules['schedule_users'] = 'nullable|array';
        foreach (ScheduleUser::getValidationRules() as $key => $rule) {
            $rules["schedule_users.*.$key"] = $rule;
        }
        return $rules;
    }
}
