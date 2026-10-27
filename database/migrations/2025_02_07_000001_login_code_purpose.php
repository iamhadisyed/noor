<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A code asked for to delete an account must never work as a sign-in code, or the other way round.
        Schema::table('login_codes', fn (Blueprint $t) => $t->string('purpose', 10)->default('login')->after('email'));
    }

    public function down(): void
    {
        Schema::table('login_codes', fn (Blueprint $t) => $t->dropColumn('purpose'));
    }
};
