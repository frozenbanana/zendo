<?php

namespace App\Modules\Registration\Jobs;

use App\Modules\Notifications\Models\OutboxEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessOutboxEntries implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        OutboxEntry::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5))
            ->chunk(100, function ($entries) {
                foreach ($entries as $entry) {
                    try {
                        $entry->update(['status' => 'processing']);

                        // Dispatch to external services
                        // (Webhooks, third-party APIs, etc.)

                        $entry->update([
                            'status' => 'processed',
                            'processed_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        $entry->update([
                            'status' => 'failed',
                        ]);

                        report($e);
                    }
                }
            });
    }
}
