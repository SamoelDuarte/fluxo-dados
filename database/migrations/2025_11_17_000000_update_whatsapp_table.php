<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateWhatsappTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp')) {
            Schema::table('whatsapp', function (Blueprint $table) {
                // Remover phone_number_id se existir
                if (Schema::hasColumn('whatsapp', 'phone_number_id')) {
                    $table->dropColumn('phone_number_id');
                }
                
                // Adicionar business_id se não existir
                if (!Schema::hasColumn('whatsapp', 'business_id')) {
                    $table->string('business_id')->nullable()->after('access_token');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp')) {
            Schema::table('whatsapp', function (Blueprint $table) {
                // Remover business_id se existir
                if (Schema::hasColumn('whatsapp', 'business_id')) {
                    $table->dropColumn('business_id');
                }
                
                // Re-adicionar phone_number_id se necessário reverter
                if (!Schema::hasColumn('whatsapp', 'phone_number_id')) {
                    $table->string('phone_number_id')->nullable()->after('access_token');
                }
            });
        }
    }
}
