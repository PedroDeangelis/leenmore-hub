<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * App-wide editable settings — a simple key/value store so the admin Options tool
 * can manage values like the receipt-form announcement banner. Extensible: add a
 * new key rather than a new column or table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed the receipt-form announcement with the launch copy.
        DB::table('settings')->insert([
            'key' => 'receipt_announcement',
            'value' => '※ 식대 1일 1만원 정액제입니다 ※ 문의 : 010-5636-0510 김인영 (부재시 순차 연락드립니다)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
