<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The usage categories (사용 내역) a worker picks from on the receipt form. Admin
 * editable via the Options tool. A receipt snapshots the chosen name so its
 * history survives a category rename or delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Sensible starter set; admins manage the rest in Options.
        $now = now();
        DB::table('receipt_categories')->insert([
            ['name' => '식대', 'position' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '교통비', 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '기타', 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_categories');
    }
};
