<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_sessions')) {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_sessions', 'phone_number_id')) {
                    $table->string('phone_number_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_sessions')) {
            Schema::table('whatsapp_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('whatsapp_sessions', 'phone_number_id')) {
                    $table->dropColumn('phone_number_id');
                }
            });
        }
    }
};
