<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A therapist or teacher a parent lets see one child's progress, read-only. Set up with a short code.
        Schema::create('specialist_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);                                  // therapist | teacher
            $table->string('label', 80);                                 // "Ms Sara, speech therapist"
            $table->string('code_hash', 64)->nullable()->index();        // only while the code is waiting to be used
            $table->timestamp('code_expires_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();   // who redeemed it
            $table->string('status', 10)->default('pending');            // pending | active | revoked
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // Exercises a specialist sets for the parent to do with the child.
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('note')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 10)->default('open');               // open | done
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['child_id', 'status']);
        });

        // Exercises are text from a person outside the family, so the word filter and the review queue cover them too.
        Schema::table('content_flags', fn (Blueprint $t) => $t->unsignedBigInteger('assignment_id')->nullable());
    }

    public function down(): void
    {
        Schema::table('content_flags', fn (Blueprint $t) => $t->dropColumn('assignment_id'));
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('specialist_access');
    }
};
