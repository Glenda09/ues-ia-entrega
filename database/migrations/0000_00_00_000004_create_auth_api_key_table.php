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
        Schema::create('auth_api_key', function (Blueprint $table) {
            $table->uuid('api_key')->primary()->comment('API Key (UUID, identificador público)');
            $table->string('token_hash', 64)->nullable()->unique()->comment('SHA-256 del secreto real. El valor bruto nunca se almacena.');
            $table->string('cliente', 255)->comment('Cliente propietario del API Key');
            $table->string('id_estado', 255)->comment('Estado (FK estado.codigo)');
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');

            $table->foreign('id_estado')->references('codigo')->on('estado');
            $table->index('id_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_api_key');
    }
};
