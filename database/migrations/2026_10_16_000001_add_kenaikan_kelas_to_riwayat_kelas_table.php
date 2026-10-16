<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riwayat_kelas', function (Blueprint $table) {
            $table->string('status_kenaikan_kelas', 16)->nullable();
            $table->string('kelas_tujuan', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_kelas', function (Blueprint $table) {
            $table->dropColumn(['status_kenaikan_kelas', 'kelas_tujuan']);
        });
    }
};
