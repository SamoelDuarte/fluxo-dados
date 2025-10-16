<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneNumberIdToWhatsappTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp')) {
            Schema::table('whatsapp', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp', 'phone_number_id')) {
                    $table->string('phone_number_id')->nullable()->after('access_token');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp')) {
            Schema::table('whatsapp', function (Blueprint $table) {
                if (Schema::hasColumn('whatsapp', 'phone_number_id')) {
                    $table->dropColumn('phone_number_id');
                }
            });
        }
    }
}
