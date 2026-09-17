<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\ReturnRequest;
use App\Notifications\ReturnRequestUpdated;
use Illuminate\Support\Facades\Log;

class ReturnRequestNotifier
{
    public function notify(ReturnRequest $claim, string $event): void
    {
        $claim->loadMissing('user');
        $notification = new ReturnRequestUpdated($claim, $event);

        try {
            $claim->user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            if ($claim->user->phone_number && $claim->user->phone_verified_at) {
                SendSmsJob::dispatch($claim->user->phone_number, $notification->toSmsMessage());
            }
        } catch (\Throwable $e) {
            report($e);
        }

        Log::info('return_request_updated', [
            'claim_id' => $claim->id,
            'event' => $event,
            'status' => $claim->status,
        ]);
    }
}
