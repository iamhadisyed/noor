<?php

namespace Database\Seeders;

use App\Models\GameItem;
use Illuminate\Database\Seeder;

/** The animal names for the sound games. Pictures and sounds are added by the administrator in the console. */
class GameItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('games.animals') as $slug => [$en, $ur]) {
            GameItem::firstOrCreate(['slug' => $slug], ['kind' => 'animal', 'name_en' => $en, 'name_ur' => $ur]);
        }
    }
}
