<x-filament::page>
    <div class="flex min-h-[60vh] items-center justify-center py-8">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="h-1.5 bg-gradient-to-r from-amber-400 via-primary-500 to-primary-600"></div>

            <div class="px-6 py-10 text-center sm:px-10">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 ring-8 ring-amber-50/50 dark:bg-amber-500/10 dark:ring-amber-500/5">
                    <x-filament::icon icon="heroicon-o-clock" class="h-8 w-8 text-amber-500 dark:text-amber-400" />
                </div>

                <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ __('Account Pending Approval') }}
                </h1>

                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    {{ __('Your account has been successfully created and is now awaiting approval from an administrator.') }}
                </p>

                <ol class="mx-auto mt-8 max-w-xs space-y-4 text-left">
                    <li class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success-50 dark:bg-success-500/10">
                            <x-filament::icon icon="heroicon-m-check" class="h-5 w-5 text-success-600 dark:text-success-400" />
                        </span>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Account created successfully') }}</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-500/10">
                            <x-filament::icon icon="heroicon-m-ellipsis-horizontal" class="h-5 w-5 animate-pulse text-amber-600 dark:text-amber-400" />
                        </span>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Awaiting administrator approval') }}</span>
                    </li>
                </ol>

                <div class="mt-8 flex items-start gap-3 rounded-xl bg-primary-50 p-4 text-left dark:bg-primary-500/10">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="mt-0.5 h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                    <p class="text-sm text-primary-700 dark:text-primary-300">
                        {{ __('You will be notified once your account has been reviewed and approved.') }}
                    </p>
                </div>

                <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="mt-8">
                    @csrf
                    <x-filament::button
                        type="submit"
                        color="gray"
                        icon="heroicon-m-arrow-left-start-on-rectangle"
                        class="w-full sm:w-auto"
                    >
                        {{ __('filament-panels::layout.actions.logout.label') }}
                    </x-filament::button>
                </form>

                @if ($user = filament()->auth()->user())
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ $user->email }}
                    </p>
                @endif

                <p class="mt-6 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('If you have any questions, please contact our support team.') }}
                </p>
            </div>
        </div>
    </div>
</x-filament::page>
