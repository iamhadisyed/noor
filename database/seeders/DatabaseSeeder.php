<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AacCardSeeder::class);
        $this->call(LessonTemplateSeeder::class);
        $this->call(GameItemSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
