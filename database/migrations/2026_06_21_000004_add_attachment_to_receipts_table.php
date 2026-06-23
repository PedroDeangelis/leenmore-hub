<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A single optional attachment per receipt (a photo/audio/PDF of the receipt).
 * Stored as a disk-relative path on the private `local` disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('attachment')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};
