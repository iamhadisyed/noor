<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal FCM HTTP v1 client (no SDK, suits shared hosting).
 * Signs a service-account JWT with openssl and exchanges it for an OAuth token.
 */
class FcmService
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::where('user_id', $userId)->get();
        if ($tokens->isEmpty() || ! ($access = $this->accessToken())) {
            return 0;
        }

        $project = config('kindo.fcm.project_id') ?: ($this->credentials()['project_id'] ?? null);
        $sent = 0;

        foreach ($tokens as $device) {
            $response = Http::withToken($access)->timeout(10)->post(
                "https://fcm.googleapis.com/v1/projects/{$project}/messages:send",
                ['message' => $this->payload($device, $title, $body, $data)]
            );

            if ($response->successful()) {
                $sent++;
            } elseif (in_array($response->status(), [400, 404], true)
                && str_contains((string) $response->body(), 'UNREGISTERED')) {
                $device->delete();
            } else {
                Log::warning("FCM send failed ({$response->status()}): " . $response->body());
            }
        }

        return $sent;
    }

    public function payload(DeviceToken $device, string $title, string $body, array $data): array
    {
        $message = [
            'token' => $device->token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => array_map('strval', $data),
            'android' => [
                'priority' => 'HIGH',
                'notification' => ['channel_id' => config('kindo.fcm.channel_id'), 'sound' => 'alert_tone'],
            ],
        ];

        if ($device->device_type === 'ios') {
            $message['apns'] = [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => ['critical' => 1, 'name' => 'default', 'volume' => 1.0], 'interruption-level' => 'critical']],
            ];
        }

        return $message;
    }

    protected function credentials(): array
    {
        $path = config('kindo.fcm.service_account');
        return is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }

    protected function accessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3000, function () {
            $c = $this->credentials();
            if (empty($c['private_key']) || empty($c['client_email'])) {
                Log::warning('FCM service account missing; push skipped.');
                return null;
            }

            $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
                'iss' => $c['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $sig, $c['private_key'], OPENSSL_ALGO_SHA256);

            $res = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . $b64($sig),
            ]);

            return $res->successful() ? $res->json('access_token') : null;
        });
    }
}
