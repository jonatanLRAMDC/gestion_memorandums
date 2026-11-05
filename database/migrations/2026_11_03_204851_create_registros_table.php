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
        Schema::create('registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('memorandos')->restrictOnDelete();
            $table->foreignId('gerencia_id')->constrained('gerencias')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->date('fecha_movimiento');
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['memorando_id', 'gerencia_id', 'tipo']);
            $table->index('fecha_movimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registros');
    }
};
