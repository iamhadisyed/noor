<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each time a child finishes a calm or movement activity, so a parent can see which ones help.
        Schema::create('calm_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('activity', 40);
            $table->unsignedSmallInteger('seconds')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['child_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calm_sessions');
    }
};
