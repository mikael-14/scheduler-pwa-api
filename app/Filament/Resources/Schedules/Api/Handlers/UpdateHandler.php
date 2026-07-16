<?php
namespace App\Filament\Resources\Schedules\Api\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Schedules\Api\Requests\UpdateScheduleRequest;
use App\Models\ScheduleUser;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = ScheduleResource::class;
    protected static string $permission = 'update_schedule';

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Schedule
     *
     * @param UpdateScheduleRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateScheduleRequest $request)
    {
        $id = $request->route('id');
        
        return DB::transaction(function () use ($request, $id) {
            $model = static::getModel()::find($id);

            if (!$model) return static::sendNotFoundResponse();

            $model->fill($request->all());
            $model->save();

            if ($request->has('schedule_users')) {
                $incomingUsers = $request->input('schedule_users');
                $keptIds = [];

                foreach ($incomingUsers as $userData) {
                    // updateOrCreate uses the user_id as a key to find existing records 
                    // within this specific schedule.
                    $userRecord = $model->schedule_users()->updateOrCreate(
                        ['user_id' => $userData['user_id']],
                        $userData
                    );
                    $keptIds[] = $userRecord->id;
                }

                // Remove any users that were previously attached but are not in the current request
                $model->schedule_users()->whereNotIn('id', $keptIds)->delete();
            }

            return static::sendSuccessResponse($model->load('schedule_users'), "Successfully Update Resource");
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