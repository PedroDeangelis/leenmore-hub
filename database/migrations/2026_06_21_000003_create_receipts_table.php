<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * An expense receipt submitted by a field worker (영수증 제출) — primarily the daily
 * meal allowance. The worker and category names are denormalized so the history
 * survives a user/category delete (mirrors submissions.user_name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            // The submitting worker; name kept denormalized so history survives a delete.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();

            // The chosen usage category; name snapshotted (categories are editable).
            $table->foreignId('receipt_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name')->nullable();

            $table->date('date');
            $table->string('vendor');
            $table->unsignedBigInteger('amount'); // KRW won — no decimals.
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
