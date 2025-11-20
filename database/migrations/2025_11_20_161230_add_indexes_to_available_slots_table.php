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
        Schema::table('available_slots', function (Blueprint $table) {
            // Adiciona índice para o campo day_of_week (melhora performance nas queries)
            $table->index('day_of_week');
            
            // Adiciona índice composto para queries de intervalo de tempo
            // Esto es importante para as queries que usam start_time e end_time
            $table->index(['day_of_week', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('available_slots', function (Blueprint $table) {
            // Remove os índices
            $table->dropIndex(['day_of_week']);
            $table->dropIndex(['day_of_week', 'start_time', 'end_time']);
        });
    }
};
