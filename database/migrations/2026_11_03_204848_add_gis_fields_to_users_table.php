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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('gerencia_id')->nullable()->after('id')->constrained('gerencias')->restrictOnDelete();
            $table->string('rol', 20)->default('consulta')->after('password');
            $table->boolean('activo')->default(true)->after('rol');
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('activo');
            $table->timestamp('bloqueado_en')->nullable()->after('intentos_fallidos');
            $table->boolean('debe_cambiar_contrasena')->default(false)->after('bloqueado_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gerencia_id');
            $table->dropColumn(['rol', 'activo', 'intentos_fallidos', 'bloqueado_en', 'debe_cambiar_contrasena']);
        });
    }
};
