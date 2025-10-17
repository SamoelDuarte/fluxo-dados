<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contato_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contato_id')->constrained('contatos')->onDelete('cascade');
            $table->string('file_path');
            $table->integer('total_rows')->nullable();
            $table->integer('processed_rows')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contato_imports');
    }
};
