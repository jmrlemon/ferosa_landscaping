<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $to, string $message): bool
    {
        $driver = config('services.sms.driver', 'log');

        return match ($driver) {
            'twilio' => $this->sendTwilio($to, $message),
            'textbee' => $this->sendTextBee($to, $message),
            default => $this->sendLog($to, $message),
        };
    }

    private function sendLog(string $to, string $message): bool
    {
        Log::info("SMS to {$to}: {$message}");

        return true;
    }

    private function sendTwilio(string $to, string $message): bool
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            Log::error('Twilio SMS credentials are incomplete.');

            return false;
        }

        try {
            $response = Http::timeout(10)->retry(2, 250)
                ->withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $message,
                ]);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return $response->successful();
    }

    private function sendTextBee(string $to, string $message): bool
    {
        // Ensure international format (+639XXXXXXXXX)
        $number = preg_replace('/[^0-9+]/', '', $to);
        if (str_starts_with($number, '09')) {
            $number = '+63'.substr($number, 1);
        } elseif (str_starts_with($number, '63')) {
            $number = '+'.$number;
        }

        $deviceId = config('services.textbee.device_id');
        $apiKey = config('services.textbee.api_key');

        if (! $deviceId || ! $apiKey) {
            Log::error('TextBee SMS credentials are incomplete.');

            return false;
        }

        try {
            $response = Http::timeout(10)->retry(2, 250)
                ->withHeaders(['x-api-key' => $apiKey])
                ->post("https://api.textbee.dev/api/v1/gateway/devices/{$deviceId}/sendSMS", [
                    'receivers' => [$number],
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        if (! $response->successful()) {
            Log::error('TextBee SMS failed', ['status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->successful();
    }
}
