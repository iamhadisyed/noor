<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A six-digit PIN sign-in for the owner's admin account and the public demo accounts (set only from the server console or the seeders).
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable();
            $table->unsignedSmallInteger('pin_failed')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->boolean('is_demo')->default(false);
        });

        // Screen-time rules for a child (Pro): daily limit, quiet hours. Usage is counted per day.
        Schema::table('children', function (Blueprint $table) {
            $table->json('screen_time')->nullable();
        });

        Schema::create('screen_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('seconds')->default(0);
            $table->unsignedInteger('bonus_seconds')->default(0);   // minutes a parent gave on top of the limit that day
            $table->timestamps();
            $table->unique(['child_id', 'day']);
        });

        // "Make me Pro": a request, the payment details the parent reports, and what the owner decided.
        Schema::create('upgrade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('requested');   // requested | paid_reported | approved | rejected
            $table->string('phone', 30)->nullable();
            $table->text('note')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('payment_note')->nullable();
            $table->timestamp('paid_reported_at')->nullable();
            $table->unsignedSmallInteger('days_granted')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_requests');
        Schema::dropIfExists('screen_time_logs');
        Schema::table('children', fn (Blueprint $t) => $t->dropColumn('screen_time'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['pin_hash', 'pin_failed', 'pin_locked_until', 'is_demo']));
    }
};
