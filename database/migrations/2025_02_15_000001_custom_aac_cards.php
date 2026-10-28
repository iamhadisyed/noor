<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parents can make their own picture cards. Their pictures and voices are private files, like family cards.
        Schema::table('aac_cards', function (Blueprint $table) {
            $table->string('audio_mime', 40)->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        Schema::table('aac_cards', function (Blueprint $table) {
            $table->dropColumn('audio_mime');
        });
    }
};
