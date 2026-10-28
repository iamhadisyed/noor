<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How each letter went in one practice session, so a parent can see which letters are steady and which need help.
        Schema::create('session_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('practice_sessions')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('letter', 20);
            $table->unsignedSmallInteger('attempts');
            $table->unsignedSmallInteger('successes');
            $table->unsignedSmallInteger('assisted')->default(0);      // tries that needed the dotted guide
            $table->decimal('deviation', 6, 2)->nullable();            // average drift from the ideal stroke
            $table->timestamp('created_at')->useCurrent();
            $table->index(['child_id', 'letter', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_letters');
    }
};
