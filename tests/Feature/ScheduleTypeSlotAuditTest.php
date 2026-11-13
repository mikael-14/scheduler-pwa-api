<?php

namespace Tests\Feature;

use App\Filament\Resources\ScheduleTypes\Pages\ViewScheduleType;
use App\Filament\Resources\ScheduleTypes\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ActivityRelationManager;
use App\Models\ScheduleType;
use App\Models\ScheduleTypeSlot;
use App\Models\User;
use App\Services\ScheduleTypeRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Slot changes are audited with their author, and listed in the type's History (also the
 * removed ones) and in the author's Activity.
 */
class ScheduleTypeSlotAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ScheduleTypeRules::flush();
        // Laravel Auditing skips console runs (tests count as console)
        config(['audit.console' => true]);
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        Filament::setCurrentPanel('admin');
    }

    private function userWith(array $permissions, string $name = 'Someone'): User
    {
        $user = User::factory()->create(['approved_at' => now(), 'status' => true, 'name' => $name]);
        $role = Role::create(['name' => 'role-' . $user->id, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $slots): array
    {
        return [
            'name' => 'Feira', 'color' => '#aa3300', 'status' => true, 'range' => true, 'all_day' => false,
            'min_time' => '08:00', 'max_time' => '20:00', 'uses_slots' => true, 'slots' => $slots,
        ];
    }

    public function test_slot_changes_are_audited_and_listed_in_the_types_history(): void
    {
        $day = now()->addDays(3)->format('Y-m-d');
        $editor = $this->userWith(['view_schedule_type', 'create_schedule_type', 'update_schedule_type'], 'Slot Editor');
        Sanctum::actingAs($editor);

        $id = $this->postJson('/api/admin/schedule-types', $this->payload([
            ['day' => $day, 'from' => '09:00', 'to' => '11:00'],
            ['day' => $day, 'from' => '11:00', 'to' => '13:00'],
        ]))->assertOk()->json('data.id');
        $type = ScheduleType::findOrFail($id);
        [$kept, $removed] = $type->slots()->get()->all();

        $this->putJson("/api/admin/schedule-types/{$type->id}", $this->payload([
            ['id' => $kept->id, 'day' => $day, 'from' => '09:00', 'to' => '11:00', 'capacity' => 3, 'comment' => 'Portão B'],
        ]))->assertOk();
        $this->assertNull(ScheduleTypeSlot::find($removed->id));

        // created twice, updated once, deleted once: all by the editor
        $slotAudits = Audit::where('auditable_type', ScheduleTypeSlot::class)->orderBy('id')->get();
        $this->assertSame(['created', 'created', 'updated', 'deleted'], $slotAudits->pluck('event')->all());
        $this->assertSame([$editor->id], $slotAudits->pluck('user_id')->unique()->values()->all());
        $updated = $slotAudits->firstWhere('event', 'updated');
        $this->assertSame('Portão B', $updated->new_values['comment']);
        $this->assertSame(3, (int) $updated->new_values['capacity']);

        // another type's slot: not in this type's History
        $other = ScheduleType::create(['name' => 'Outra', 'status' => true, 'range' => true]);
        $other->slots()->create(['start' => "$day 09:00", 'end' => "$day 11:00"]);
        $theirs = Audit::where('auditable_type', ScheduleTypeSlot::class)->latest('id')->first();

        $auditor = $this->userWith(['view_any_schedule_type', 'view_schedule_type', 'view_any_audit', 'view_audit', 'view_any_user', 'view_user']);
        $this->actingAs($auditor, 'web');

        $mine = Audit::where(fn($q) => $q->where('auditable_type', ScheduleType::class)->where('auditable_id', $type->id))
            ->orWhereIn('id', $slotAudits->pluck('id'))
            ->get();
        Livewire::test(AuditsRelationManager::class, ['ownerRecord' => $type, 'pageClass' => ViewScheduleType::class])
            ->assertCanSeeTableRecords($mine)
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertSee(__('Slot') . ' ' . now()->addDays(3)->format(config('app.date_format')) . ' 11:00–13:00') // the removed one
            ->sortTable('created_at')
            ->assertCanNotSeeTableRecords([$theirs]);

        // the editor's Activity names the slots and their type
        Livewire::test(ActivityRelationManager::class, ['ownerRecord' => $editor, 'pageClass' => ViewUser::class])
            ->assertCanSeeTableRecords($slotAudits)
            ->assertSee(__('Slot') . ' ' . now()->addDays(3)->format(config('app.date_format')) . ' 09:00–11:00 · Feira');
    }
}
