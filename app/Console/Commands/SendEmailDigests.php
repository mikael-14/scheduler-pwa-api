<?php

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Models\User;
use App\Notifications\DeliveryLog;
use App\Notifications\EmailDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends each person one email with the news that waited for it (mail_status = digest, see
 * AppNotification::digestible), skipping what they already read in the app. Runs twice a day
 * (routes/console.php); the people who chose one summary a day only get the run with --with-daily.
 */
class SendEmailDigests extends Command
{
    protected $signature = 'notifications:digest {--with-daily : also the people who chose one summary a day}';

    protected $description = 'Email each person a summary of the notifications they have not seen yet';

    public function handle(): int
    {
        // already seen in the app: no need to email it any more
        $this->pending()->whereNotNull('read_at')->update(['mail_status' => null]);

        $sent = 0;
        User::query()
            ->whereIn('id', $this->pending()->where('notifiable_type', (new User)->getMorphClass())->distinct()->pluck('notifiable_id'))
            ->get()
            ->each(function (User $user) use (&$sent) {
                $rows = $this->pending()->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id);

                // no address, account off, or every email turned off since: nothing will go out
                if (! filled($user->email) || ! $user->status || (! $user->email_notification && ! $user->all_email_notification)) {
                    $rows->update(['mail_status' => null]);

                    return;
                }

                if ($user->email_digest === 'daily' && ! $this->option('with-daily')) {
                    return;
                }

                $items = $user->notifications()
                    ->where('mail_status', DeliveryStatus::Digest->value)
                    ->whereNull('read_at')
                    ->reorder('created_at')
                    ->get();
                if ($items->isEmpty()) {
                    return;
                }

                try {
                    $user->notifyNow(new EmailDigest($items));
                    $rows->whereIn('id', $items->pluck('id'))
                        ->update(['mail_status' => DeliveryStatus::Sent->value, 'mail_sent_at' => now()]);
                    $sent++;
                } catch (Throwable $e) {
                    // recorded on each piece of news (kept out of later summaries; see the delivery log)
                    $items->each(fn($item) => DeliveryLog::mailFailed($item->id, $e));
                    report($e);
                }
            });

        $this->info("Summaries sent: {$sent}");

        return self::SUCCESS;
    }

    private function pending()
    {
        return DB::table('notifications')->where('mail_status', DeliveryStatus::Digest->value);
    }
}
