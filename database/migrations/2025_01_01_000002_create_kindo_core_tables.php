<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('date_of_birth');
            $table->enum('neurotype', ['neurotypical', 'autism_spectrum', 'speech_delay', 'adhd', 'down_syndrome'])->default('neurotypical');
            $table->boolean('sensory_mode')->default(false);
            $table->string('avatar_id', 50)->default('avatar_1');
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 500)->unique();
            $table->enum('device_type', ['android', 'ios'])->default('android');
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('relation_title', 100);
            $table->string('display_name', 100);
            $table->string('photo_path');
            $table->string('audio_prompt_path');
            $table->timestamps();
        });

        Schema::create('aac_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = global stock card
            $table->enum('category', ['emergency', 'needs', 'food', 'emotions', 'activities']);
            $table->string('english_label', 100);
            $table->string('urdu_label', 100);
            $table->string('icon_path');
            $table->string('audio_path')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->boolean('is_pro')->default(false);
            $table->timestamps();
        });

        Schema::create('child_needs_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained('aac_cards')->nullOnDelete();
            $table->string('need_type', 50);
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['child_id', 'created_at']);
        });

        Schema::create('syllabus_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->enum('language', ['urdu', 'english']);
            $table->unsignedInteger('week_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('syllabus_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_module_id')->constrained()->cascadeOnDelete();
            $table->string('word', 100);
            $table->string('phonetic_hint', 100)->nullable();
            $table->json('character_decomposition')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('difficulty')->default(1);
        });

        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('syllabus_modules')->nullOnDelete();
            $table->enum('language', ['urdu', 'english']);
            $table->unsignedInteger('total_attempts');
            $table->unsignedInteger('successful_attempts');
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('practice_mistake_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('practice_sessions')->cascadeOnDelete();
            $table->string('target_string', 100);
            $table->string('attempted_string', 100);
            $table->decimal('stroke_deviation_score', 5, 2)->nullable();
            $table->enum('mistake_type', ['phonetic_substitution', 'letter_omission', 'dot_misplacement', 'stroke_inversion']);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_diagnostic_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->date('report_week_start');
            $table->string('language_dominance', 50)->nullable();
            $table->json('phonetic_blindspots');
            $table->json('potty_timing_analysis');
            $table->text('sensory_behavior_summary');
            $table->json('actionable_parent_exercises');
            $table->json('dietary_chaining_tips')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['child_id', 'report_week_start']);
        });
    }

    public function down(): void
    {
        foreach (['ai_diagnostic_reports', 'practice_mistake_logs', 'practice_sessions', 'syllabus_words', 'syllabus_modules',
                  'child_needs_logs', 'aac_cards', 'family_members', 'device_tokens', 'children'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
