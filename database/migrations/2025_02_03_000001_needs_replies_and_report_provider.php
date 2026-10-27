<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_needs_logs', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('acknowledged_at');
            $table->foreignId('acknowledged_by')->nullable()->after('seen_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('ai_diagnostic_reports', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('language_dominance');   // groq | gemini, for admin reporting
        });
    }

    public function down(): void
    {
        Schema::table('ai_diagnostic_reports', fn (Blueprint $t) => $t->dropColumn('provider'));
        Schema::table('child_needs_logs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('acknowledged_by');
            $t->dropColumn('seen_at');
        });
    }
};
