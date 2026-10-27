<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per thing that changed for a parent. The id is the sync cursor.
        Schema::create('sync_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 20);                 // child | lesson | family_member | need
            $table->unsignedBigInteger('entity_id');
            $table->string('op', 8);                      // upsert | delete
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
        });

        // Lets a phone retry a write after a dropped connection without doing it twice.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('method', 8);
            $table->string('path', 200);
            $table->unsignedSmallInteger('status');
            $table->longText('body');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('sync_changes');
    }
};
