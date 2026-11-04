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
        Schema::create('gerencias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->string('siglas', 20)->nullable()->unique();
            $table->string('titular_nombre', 150)->nullable();
            $table->string('titular_cargo', 150)->nullable();
            $table->boolean('activa')->default(true);
            $table->boolean('usa_sistema')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gerencias');
    }
};
