<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->json('notification_prefs')->nullable());

        // The parent confirms they may use this person's photo and voice. Kept as a record.
        Schema::table('family_members', function (Blueprint $t) {
            $t->timestamp('consented_at')->nullable();
            $t->foreignId('consented_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $t) {
            $t->dropConstrainedForeignId('consented_by');
            $t->dropColumn('consented_at');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notification_prefs'));
    }
};
