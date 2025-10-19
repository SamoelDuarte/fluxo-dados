<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $table->timestamp('token_expires_at')->nullable(); // Quando o token expira
            $table->boolean('is_connected')->default(false); // Se está conectado
            $table->timestamps();
        });

        // Inserir dados fake
        DB::table('whatsapp')->insert([
            'app_id' => '1338469367894948',
            'app_secret' => '11da6ea1201d0bba8cc7763453f9d442',
            'redirect_uri' => 'https://fluxo-neocob.betasolucao.com.br/whatsapp/callback',
            'access_token' => 'EAATBVK83U6QBPrSkgEZBtwiRVRELFA4AngtfDjzQaIcnELadHKNx3SpZA2mPi1GQirZBy7TdMMv16owoswoGtPwIZClsiRKPMeZBfOXQm95PZBId0QZCu9thVTKw65Mehb0OSXZCAWngjYHeefbED2QCKSVrOE0BkQky6KT6D54UuA0MXULLzks7t4ZBS3Hra',
             'token_expires_at' => now()->addYear(),
            'is_connected' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp');
    }
}
