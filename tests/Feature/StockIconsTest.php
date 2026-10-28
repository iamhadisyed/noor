<?php

namespace Tests\Feature;

use App\Models\AacCard;
use Database\Seeders\AacCardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_stock_card_has_a_square_transparent_picture_shipped(): void
    {
        $this->seed(AacCardSeeder::class);

        foreach (AacCard::whereNull('child_id')->get() as $card) {
            $file = public_path($card->icon_path);
            $this->assertFileExists($file, "no picture for {$card->english_label}");
            [$w, $h, $type] = getimagesize($file);
            $this->assertSame([512, 512, IMAGETYPE_PNG], [$w, $h, $type], $card->english_label);
        }
    }

    public function test_the_command_keeps_existing_artwork_unless_forced(): void
    {
        $dir = sys_get_temp_dir().'/kindo-icons-'.uniqid();
        $this->artisan('kindo:stock-icons', ['--path' => $dir])->assertExitCode(0);
        $this->assertCount(13, glob("{$dir}/*.png"));

        file_put_contents("{$dir}/water.png", 'designer artwork');
        $this->artisan('kindo:stock-icons', ['--path' => $dir])->assertExitCode(0);
        $this->assertSame('designer artwork', file_get_contents("{$dir}/water.png"));

        $this->artisan('kindo:stock-icons', ['--path' => $dir, '--force' => true])->assertExitCode(0);
        $this->assertNotSame('designer artwork', file_get_contents("{$dir}/water.png"));
        array_map('unlink', glob("{$dir}/*.png"));
        rmdir($dir);
    }

    public function test_the_printable_sheet_includes_the_pictures(): void
    {
        $this->seed(AacCardSeeder::class);

        $html = view('pdf.pecs-printable', ['cards' => AacCard::whereNull('child_id')->get()])->render();

        foreach (['water', 'potty', 'milk', 'bed', 'play'] as $name) {
            $this->assertStringContainsString(public_path("aac/{$name}.png"), $html);
        }
    }
}
