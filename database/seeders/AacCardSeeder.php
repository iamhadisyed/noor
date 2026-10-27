<?php

namespace Database\Seeders;

use App\Models\AacCard;
use Illuminate\Database\Seeder;

class AacCardSeeder extends Seeder
{
    public function run(): void
    {
        // [category, english, urdu, emergency, pro]. First four are the free-tier core set.
        $cards = [
            ['needs', 'Water', 'پانی', false, false],
            ['needs', 'Potty', 'بیت الخلا', true, false],
            ['food', 'Milk', 'دودھ', false, false],
            ['needs', 'Bed', 'بستر', false, false],
            ['food', 'Food', 'کھانا', false, true],
            ['needs', 'Sleep', 'نیند', false, true],
            ['needs', 'Hug', 'گلے لگاؤ', false, true],
            ['emergency', 'Pain', 'درد', true, true],
            ['emergency', 'Help', 'مدد', true, true],
            ['emotions', 'Happy', 'خوش', false, true],
            ['emotions', 'Sad', 'اداس', false, true],
            ['emotions', 'Scared', 'ڈر', false, true],
            ['activities', 'Play', 'کھیل', false, true],
        ];

        foreach ($cards as [$cat, $en, $ur, $emergency, $pro]) {
            AacCard::updateOrCreate(
                ['child_id' => null, 'english_label' => $en],
                ['category' => $cat, 'urdu_label' => $ur, 'icon_path' => 'aac/' . strtolower($en) . '.png',
                 'is_emergency' => $emergency, 'is_pro' => $pro]
            );
        }
    }
}
