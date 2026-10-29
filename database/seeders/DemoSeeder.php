<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DemoContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Two demo accounts with full Pro and no purchase, each with a ready family, for showing the apps:
 *
 *   Parent app:  demo.parent@kindo.local  /  PIN 135790   (Ayan, 6, autism; Zara, 8)
 *   Child app:   demo.child@kindo.local   /  PIN 246801   (Mina, 5; Hamza, 9)
 *
 * The child app signs in with the parent's account, then the child taps their name; these two are just two separate households.
 * They are reset every night (`kindo:demo-reset`) and cannot send email, use AI or delete the account. The PINs are public on purpose:
 * keep nothing real in them. Switched off in production unless DEMO_ENABLED=true.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('kindo.demo.enabled')) {
            $this->command?->info('Demo accounts are switched off (DEMO_ENABLED).');

            return;
        }
        $this->account('parent', 'Demo Parent', [
            ['name' => 'Ayan', 'born' => now()->subYears(6)->subMonths(3)->toDateString(), 'neurotype' => 'autism_spectrum', 'track' => 'special'],
            ['name' => 'Zara', 'born' => now()->subYears(8)->subMonths(1)->toDateString(), 'neurotype' => 'neurotypical', 'track' => 'standard'],
        ]);
        $this->account('child', 'Demo Family', [
            ['name' => 'Mina', 'born' => now()->subYears(5)->subMonths(2)->toDateString(), 'neurotype' => 'neurotypical', 'track' => 'standard'],
            ['name' => 'Hamza', 'born' => now()->subYears(9)->subMonths(4)->toDateString(), 'neurotype' => 'neurotypical', 'track' => 'standard'],
        ]);
    }

    private function account(string $which, string $name, array $kids): void
    {
        $cfg = config("kindo.demo.{$which}");
        $user = User::firstOrNew(['email' => $cfg['email']]);
        $user->children()->get()->each(function ($c) { app(\App\Services\AccountEraser::class)->eraseChild($c); });
        $user->forceFill(['name' => $name, 'role' => 'parent', 'status' => 'active', 'is_demo' => true, 'subscription_tier' => 'pro', 'subscription_expires_at' => null,
            'email_verified_at' => now(), 'terms_accepted_at' => now(), 'terms_version' => config('legal.version'), 'pin_hash' => Hash::make($cfg['pin']), 'pin_failed' => 0, 'pin_locked_until' => null])->save();
        app(DemoContent::class)->fill($user, $kids);
        $this->command?->info("Demo {$which}: {$cfg['email']}  PIN {$cfg['pin']}  (full Pro)");
    }
}
