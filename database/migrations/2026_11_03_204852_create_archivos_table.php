<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('memorandos')->restrictOnDelete();
            $table->foreignId('registro_id')->nullable()->constrained('registros')->restrictOnDelete();
            $table->string('ruta_interna');
            $table->string('nombre_original');
            $table->char('sha256', 64)->index();
            $table->unsignedBigInteger('tamano_bytes');
            $table->unsignedSmallInteger('paginas')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('vigente')->default(true);
            $table->foreignId('subido_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};
