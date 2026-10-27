<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', fn (Blueprint $t) => $t->string('audio_mime', 40)->nullable()->after('audio_prompt_path'));
    }

    public function down(): void
    {
        Schema::table('family_members', fn (Blueprint $t) => $t->dropColumn('audio_mime'));
    }
};
