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
        Schema::table('auth_usuario', function (Blueprint $table) {
            $table->text('tfa_secret')
                ->nullable()
                ->after('tfa_activo')
                ->comment('Clave secreta para Google Authenticator');

            $table->string('2fa_tipo', 20)
                ->nullable()
                ->after('tfa_secret')
                ->comment('Tipo de doble factor activo (authenticator, email o disabled)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth_usuario', function (Blueprint $table) {
            $table->dropColumn(['tfa_secret', '2fa_tipo']);
        });
    }
};
