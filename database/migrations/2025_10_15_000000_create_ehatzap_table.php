<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEhatzapTable extends Migration
{
    public function up()
    {
        Schema::create('whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->nullable(); // ID do aplicativo
            $table->string('app_secret')->nullable(); // Secret do aplicativo
            $table->text('redirect_uri')->nullable(); // URL de callback
            $table->text('access_token')->nullable(); // Token de acesso após OAuth
            $table->text('refresh_token')->nullable(); // Token de refresh
            $table->timestamp('token_expires_at')->nullable(); // Quando o token expira
            $table->boolean('is_connected')->default(false); // Se está conectado
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp');
    }
}
