<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda el motivo de cada nueva versión del PDF y cuándo dejó de estar vigente la anterior.
     */
    public function up(): void
    {
        Schema::table('archivos', function (Blueprint $table) {
            $table->string('motivo', 500)->nullable()->after('vigente');
            $table->timestamp('reemplazado_en')->nullable()->after('motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archivos', function (Blueprint $table) {
            $table->dropColumn(['motivo', 'reemplazado_en']);
        });
    }
};
