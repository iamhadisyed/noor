<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->string('track', 20)->default('standard')->after('neurotype');   // standard | special
            $table->json('support_profile')->nullable()->after('track');
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();    // null = platform template
            $table->foreignId('child_id')->nullable()->constrained()->cascadeOnDelete();   // null = template
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('language', 10)->default('urdu');                                // urdu | english | both
            $table->string('track', 20)->default('standard');
            $table->unsignedInteger('week_number')->default(1);
            $table->string('status', 20)->default('draft');                                 // draft | published | archived
            $table->boolean('is_template')->default(false);
            $table->json('settings')->nullable();                                           // per-lesson overrides of the support profile
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['child_id', 'status']);
            $table->index(['is_template', 'track']);
        });

        Schema::create('lesson_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('type', 30);
            $table->string('title', 120);
            $table->string('instruction', 400)->nullable();
            $table->string('target', 80)->nullable();                                       // letter, word or phrase
            $table->json('options')->nullable();                                            // pick_picture: {choices:[...], correct:0}
            $table->boolean('needs_audio')->default(false);                                 // parent says "this task needs sound"
            $table->string('image_path')->nullable();
            $table->string('image_alt', 160)->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_mime', 40)->nullable();
            $table->timestamps();
            $table->index(['lesson_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_tasks');
        Schema::dropIfExists('lessons');
        Schema::table('children', fn (Blueprint $t) => $t->dropColumn(['track', 'support_profile']));
    }
};
