<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 20)->nullable();
            $table->string('status_reason', 300)->nullable();
        });

        Schema::create('content_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // the parent who made the content
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('family_member_id')->nullable();
            $table->string('reason', 20);                                            // bad_word | manual
            $table->string('term', 60)->nullable();
            $table->string('excerpt', 200)->nullable();
            $table->string('fingerprint', 40)->nullable()->index();                  // same text + term is not flagged twice
            $table->string('status', 20)->default('open');                           // open | dismissed | actioned
            $table->string('action', 30)->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        Schema::create('blocked_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 60)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_terms');
        Schema::dropIfExists('content_flags');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['terms_accepted_at', 'terms_version', 'status_reason']));
    }
};
