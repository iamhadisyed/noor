<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetPin extends Command
{
    protected $signature = 'kindo:set-pin {email} {pin?} {--remove : take the PIN away, so the account signs in with the emailed code only}';
    protected $description = 'Give an account a six-digit PIN sign-in (the owner and the demo accounts), change it, or remove it';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('No account with that email. The person must sign in once first, or use `php artisan db:seed --class=AdminSeeder`.');

            return self::FAILURE;
        }
        if ($this->option('remove')) {
            $user->forceFill(['pin_hash' => null, 'pin_failed' => 0, 'pin_locked_until' => null])->save();
            $this->info('PIN removed.');

            return self::SUCCESS;
        }
        $pin = $this->argument('pin') ?: $this->secret('New six-digit PIN');
        if (! preg_match('/^\d{6}$/', (string) $pin)) {
            $this->error('The PIN must be exactly six digits.');

            return self::FAILURE;
        }
        $user->forceFill(['pin_hash' => Hash::make($pin), 'pin_failed' => 0, 'pin_locked_until' => null])->save();
        $user->tokens()->delete();   // anyone signed in with the old PIN is signed out
        $this->info("PIN set for {$user->email}.");

        return self::SUCCESS;
    }
}
