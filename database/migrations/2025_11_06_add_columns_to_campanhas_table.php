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
        Schema::table('campanhas', function (Blueprint $table) {
            // Adiciona coluna de status se não existir
            if (!Schema::hasColumn('campanhas', 'status')) {
                $table->enum('status', ['playing', 'paused', 'stopped'])->default('paused')->after('name');
            }

            // Adiciona coluna de mensagem se não existir
            if (!Schema::hasColumn('campanhas', 'mensagem')) {
                $table->longText('mensagem')->nullable()->after('status');
            }

            // Adiciona coluna de img_campanha (foreign key para imagens_campanha) se não existir
            if (!Schema::hasColumn('campanhas', 'img_campanha')) {
                $table->unsignedBigInteger('img_campanha')->nullable()->after('mensagem');
                $table->foreign('img_campanha')
                    ->references('id')
                    ->on('imagens_campanha')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanhas', function (Blueprint $table) {
            // Remove a foreign key se existir
            if (Schema::hasColumn('campanhas', 'img_campanha')) {
                $table->dropForeign(['img_campanha']);
                $table->dropColumn('img_campanha');
            }

            // Remove coluna mensagem se existir
            if (Schema::hasColumn('campanhas', 'mensagem')) {
                $table->dropColumn('mensagem');
            }

            // Remove coluna status se existir
            if (Schema::hasColumn('campanhas', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
