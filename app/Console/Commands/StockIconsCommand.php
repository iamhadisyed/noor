<?php

namespace App\Console\Commands;

use App\Services\StockIcons;
use Illuminate\Console\Command;

/** Writes the placeholder pictures for the stock picture cards. Existing files are kept unless --force, so real artwork is never overwritten by accident. */
class StockIconsCommand extends Command
{
    protected $signature = 'kindo:stock-icons {--path= : where to write (default public/aac)} {--force : replace files that already exist}';
    protected $description = 'Draw placeholder pictures for the stock picture cards';

    public function handle(StockIcons $icons): int
    {
        $dir = $this->option('path') ?: public_path('aac');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Cannot create {$dir}");

            return self::FAILURE;
        }

        $made = $kept = 0;
        foreach (array_keys(StockIcons::CARDS) as $name) {
            $file = "{$dir}/{$name}.png";
            if (is_file($file) && ! $this->option('force')) {
                $kept++;

                continue;
            }
            file_put_contents($file, $icons->render($name));
            $made++;
        }
        $this->info("Wrote {$made} pictures to {$dir}".($kept ? " ({$kept} already there, kept)" : '').'.');

        return self::SUCCESS;
    }
}
