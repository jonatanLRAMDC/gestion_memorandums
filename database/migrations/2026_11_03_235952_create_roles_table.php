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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('protegido')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('permiso_rol', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permiso', 60);

            $table->primary(['rol_id', 'permiso']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permiso_rol');
        Schema::dropIfExists('roles');
    }
};
