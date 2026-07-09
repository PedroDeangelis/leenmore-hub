<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One recipient of a `worker_emails` batch — a worker plus the address the
 * email actually goes to (their `email_receiver` override, else their login
 * email, snapshotted at queue time). A queued SendWorkerEmail job drives the
 * status lifecycle (pending → processing → sent | failed), recording attempts
 * and the last error for the history page's retry flow. When the batch
 * includes 명부양식 attachments, the per-worker files generated at send time
 * are recorded here for audit. Port of the retired leenmore-storage
 * `email_recipients` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('email');
            $table->string('status')->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            // The 명부양식 files attached to this recipient's email, if any.
            $table->string('roster_xlsx_path')->nullable();
            $table->string('roster_pdf_path')->nullable();

            $table->timestamps();

            // The history page's stat cards and status filter.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_email_recipients');
    }
};
