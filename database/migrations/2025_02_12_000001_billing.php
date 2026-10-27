<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-time codes the owner sells or gives away (a bank or wallet payment, a school, a prize).
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();                    // stored without dashes
            $table->unsignedSmallInteger('days');
            $table->string('note', 120)->nullable();
            $table->timestamp('expires_at')->nullable();              // the code stops working after this
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Every period of Pro a person has been given or has bought. Receipts from the app stores will use source = store.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10);                             // voucher | admin | store
            $table->unsignedSmallInteger('days');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('note', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
        });

        Schema::table('users', fn (Blueprint $t) => $t->date('pro_reminder_for')->nullable());   // the expiry date we already warned about
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('pro_reminder_for'));
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('vouchers');
    }
};
