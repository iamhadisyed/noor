<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', fn (Blueprint $t) => $t->json('reward_settings')->nullable());

        // Minutes earned (+) and spent (-). The balance is the sum. One row per reason per practice session, so retries cannot pay twice.
        Schema::create('reward_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('minutes');
            $table->string('reason', 20);                 // good_score | effort | improvement | streak | parent_bonus | play
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('note', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['child_id', 'reason', 'ref_id']);
            $table->index(['child_id', 'created_at']);
        });

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('game', 20);
            $table->unsignedTinyInteger('level');
            $table->unsignedInteger('allowed_seconds');
            $table->string('status', 10)->default('open');   // open | finished
            $table->unsignedSmallInteger('rounds_played')->nullable();
            $table->unsignedSmallInteger('correct')->nullable();
            $table->unsignedInteger('seconds_played')->nullable();
            $table->unsignedSmallInteger('minutes_spent')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['child_id', 'game']);
        });

        Schema::create('game_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('game', 20);
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedTinyInteger('last_accuracy')->nullable();
            $table->timestamps();
            $table->unique(['child_id', 'game']);
        });

        Schema::create('game_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->default('animal');
            $table->string('slug', 40)->unique();
            $table->string('name_en', 60);
            $table->string('name_ur', 60);
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_mime', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_items');
        Schema::dropIfExists('game_progress');
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('reward_ledger');
        Schema::table('children', fn (Blueprint $t) => $t->dropColumn('reward_settings'));
    }
};
