<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->string('message_id')->nullable(); // wamid...
            $table->enum('direction', ['in', 'out']); // in = recebida, out = enviada
            $table->text('content')->nullable(); // texto da mensagem
            $table->string('type')->default('text'); // text, image, audio etc
            $table->timestamp('timestamp')->nullable();
            $table->json('raw')->nullable(); // JSON completo da mensagem
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
