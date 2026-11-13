<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_personalizacion', function (Blueprint $table) {
            $table->string('clave')->primary();
            $table->json('textos')->nullable();
            $table->json('assets')->nullable();
            $table->string('color_primario', 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_personalizacion');
    }
};
