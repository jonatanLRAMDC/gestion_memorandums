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
        Schema::create('memorando_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorando_id')->constrained('memorandos')->cascadeOnDelete();
            $table->foreignId('gerencia_id')->constrained('gerencias')->restrictOnDelete();
            $table->string('persona_nombre', 150)->nullable();
            $table->string('tipo', 10)->default('para');
            $table->timestamps();

            $table->unique(['memorando_id', 'gerencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorando_destinos');
    }
};
