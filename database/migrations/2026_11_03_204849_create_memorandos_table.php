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
        Schema::create('memorandos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 60)->unique();
            $table->string('siglas', 20);
            $table->string('correlativo', 10);
            $table->unsignedInteger('correlativo_valor');
            $table->unsignedSmallInteger('anio');
            $table->foreignId('gerencia_emisora_id')->constrained('gerencias')->restrictOnDelete();
            $table->string('remitente_nombre', 150)->nullable();
            $table->string('remitente_cargo', 150)->nullable();
            $table->string('asunto');
            $table->date('fecha_memo');
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['siglas', 'correlativo_valor', 'anio']);
            $table->index('fecha_memo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorandos');
    }
};
