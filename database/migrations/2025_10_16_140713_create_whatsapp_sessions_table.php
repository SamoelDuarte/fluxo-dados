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
    Schema::create('whatsapp_sessions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
        $table->foreignId('flow_id')->nullable()->constrained('whatsapp_flows')->nullOnDelete();
        $table->foreignId('current_step_id')->nullable()->constrained('whatsapp_flow_steps')->nullOnDelete();
        $table->json('context')->nullable(); // guarda dados do fluxo (ex: cpf digitado)
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
