<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One composed "email to worker" (활동가에게 이메일 보내기) batch: the subject/body
 * an admin wrote on the compose page plus a snapshot of everything selected
 * with it — resource links, resource file attachments, and the 명부양식 (roster
 * form) toggles/sort. Delivery itself is tracked per recipient in
 * `worker_email_recipients`; `status` here is the roll-up. This is the Laravel
 * port of the retired leenmore-storage `email_jobs` (+ `email_links` /
 * `email_attachments`, collapsed into JSON snapshots so history survives
 * resources being deleted later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject');
            $table->text('body');

            // Write-once snapshots of the selected project resources:
            // links [{title, url}], attachments [{resource_id, filename, path}].
            $table->json('links');
            $table->json('attachments');

            // 명부양식 options: whether each recipient gets their own roster form
            // attached (XLSX / PDF), and the row order it is generated with.
            $table->boolean('include_roster_xlsx')->default(false);
            $table->boolean('include_roster_pdf')->default(false);
            $table->string('roster_sort_column')->default('no');
            $table->boolean('roster_sort_desc')->default(false);

            $table->string('status')->default('pending');

            $table->timestamps();

            // History page lists batches per project, newest first.
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_emails');
    }
};
