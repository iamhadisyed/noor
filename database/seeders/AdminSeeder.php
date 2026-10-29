<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The owner's super admin account, signed in with email and a six-digit PIN (the admin console shows everything).
 *
 *   Outside production:  admin@kindo.local  /  PIN 482916   (change them in .env with ADMIN_EMAIL and ADMIN_PIN)
 *   In production:       there is NO default. Set ADMIN_EMAIL and ADMIN_PIN in .env; if the PIN is missing a random one is made and shown once.
 *
 * Safe to run again: it never changes an existing PIN in production unless ADMIN_PIN is set. After signing in, change the PIN with
 * `php artisan kindo:set-pin <email> <pin>`.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('kindo.admin.email');
        if ($email === '') {
            $this->command?->warn('No admin account made: set ADMIN_EMAIL (and ADMIN_PIN) in .env, then run `php artisan db:seed --class=AdminSeeder`.');

            return;
        }
        $pin = config('kindo.admin.pin');
        $user = User::firstOrNew(['email' => $email]);
        $existing = $user->exists && $user->pin_hash;
        $generated = false;
        if ($pin === '') {
            if ($existing) {
                $this->command?->info("Admin {$email} already has a PIN; left as it is.");
                $user->forceFill(['role' => 'super_admin'])->save();

                return;
            }
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $generated = true;
        }
        if (! preg_match('/^\d{6}$/', $pin)) {
            $this->command?->error('ADMIN_PIN must be exactly six digits.');

            return;
        }
        $user->forceFill(['name' => $user->name ?: 'Kindo Admin', 'role' => 'super_admin', 'status' => 'active', 'email_verified_at' => $user->email_verified_at ?? now(),
            'pin_hash' => Hash::make($pin), 'pin_failed' => 0, 'pin_locked_until' => null])->save();

        $this->command?->info("Super admin: {$email}" . ($generated || app()->environment('production') ? '' : "  PIN {$pin}"));
        if ($generated) {
            $this->command?->warn("PIN {$pin}  (made for you, shown only now. Write it down and change it with `php artisan kindo:set-pin`.)");
        }
    }
}
