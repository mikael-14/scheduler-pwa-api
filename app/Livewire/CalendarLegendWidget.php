<?php

namespace App\Livewire;

use App\Enums\ScheduleStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions; // Add this
use Filament\Actions\Contracts\HasActions;         // Add this
use Filament\Widgets\Widget;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CalendarLegendWidget extends Widget implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions; // Use the trait

    protected string $view = 'livewire.calendar-legend-widget';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Flex::make([
                    Select::make('status')
                        ->multiple() // Enable multiple selection
                        ->options(ScheduleStatus::class)
                        ->preload()
                        ->placeholder('All Statuses')
                        ->columnSpan(2),
                    Select::make('user_id')
                        ->label('Users')
                        ->searchable()
                        ->multiple() // Enable multiple selection
                        ->options(User::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('All Users')
                        ->columnSpan(2),
                    ActionGroup::make([
                        Action::make('apply')
                            ->label('Apply Filters')
                            ->color('primary')
                            ->icon('heroicon-m-funnel')
                            ->action(fn() => $this->submit()),
                        Action::make('clear')
                            ->label('Clear')
                            ->icon('heroicon-m-x-mark')
                            ->color('gray')
                            ->action(function () {
                                $this->form->fill();
                                $this->submit();
                            }),
                    ])->buttonGroup(),
                ])->extraAttributes(['class' => 'fi-vertical-align-end']),
            ])->statePath('data');
    }

    public function clearFilters(): void
    {
        $this->form->fill();
        $this->submit();
    }

    public function submit(): void
    {
        // Don't forget to define the submit method so the Action can call it!
        $this->dispatch(
            'filterCalendar',
            status: $this->data['status'] ?? [],
            userIds: $this->data['user_id'] ?? []
        );
    }
    // If you want the widget to span the full width
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {

        return [
            'items' => collect(ScheduleStatus::cases())->map(fn($status) => [
                'label' => $status->getLabel(),
                'color' => $status->getColor(), //var(--danger-400)
            ]),
        ];
    }
}
