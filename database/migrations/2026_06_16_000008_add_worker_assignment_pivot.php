<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Worker assignment is now a real link: a shareholder assignment belongs to many
 * worker users. This replaces the stopgap `worker_names` JSON column — the CSV
 * import resolves each 활동가 name to a worker `users` row and stores the id here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_shareholder_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_shareholder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_shareholder_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('project_shareholders', function (Blueprint $table) {
            $table->dropColumn('worker_names');
        });
    }

    public function down(): void
    {
        Schema::table('project_shareholders', function (Blueprint $table) {
            $table->json('worker_names')->nullable()->after('contact_worker');
        });

        Schema::dropIfExists('project_shareholder_user');
    }
};
