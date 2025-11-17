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
            // Adiciona template_id (ID do template no WhatsApp)
            if (!Schema::hasColumn('campanhas', 'template_id')) {
                $table->string('template_id')->nullable()->after('name')->comment('ID do template no WhatsApp');
            }

            // Adiciona template_name (nome do template para uso no envio)
            if (!Schema::hasColumn('campanhas', 'template_name')) {
                $table->string('template_name')->nullable()->after('template_id')->comment('Nome do template para envio');
            }

            // Adiciona waba_id (ID da WABA selecionada)
            if (!Schema::hasColumn('campanhas', 'waba_id')) {
                $table->string('waba_id')->nullable()->after('template_name')->comment('ID da WABA (WhatsApp Business Account)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanhas', function (Blueprint $table) {
            if (Schema::hasColumn('campanhas', 'template_id')) {
                $table->dropColumn('template_id');
            }
            if (Schema::hasColumn('campanhas', 'template_name')) {
                $table->dropColumn('template_name');
            }
            if (Schema::hasColumn('campanhas', 'waba_id')) {
                $table->dropColumn('waba_id');
            }
        });
    }
};
