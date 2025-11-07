<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campanhas', function (Blueprint $table) {
            // Remove foreign key if it exists (check database constraints first)
            if (Schema::hasColumn('campanhas', 'img_campanha')) {
                // Get all foreign keys for this table
                $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                    WHERE TABLE_NAME = 'campanhas' AND COLUMN_NAME = 'img_campanha' AND REFERENCED_TABLE_NAME IS NOT NULL");
                
                // Drop each foreign key found
                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE campanhas DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                    } catch (\Throwable $e) {
                        // ignore if it doesn't exist
                    }
                }
                
                // Now drop the column
                $table->dropColumn('img_campanha');
            }

            // Remove mensagem column if exists
            if (Schema::hasColumn('campanhas', 'mensagem')) {
                $table->dropColumn('mensagem');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanhas', function (Blueprint $table) {
            // Recreate mensagem column
            if (!Schema::hasColumn('campanhas', 'mensagem')) {
                $table->longText('mensagem')->nullable()->after('status');
            }

            // Recreate img_campanha column and foreign key
            if (!Schema::hasColumn('campanhas', 'img_campanha')) {
                $table->unsignedBigInteger('img_campanha')->nullable()->after('mensagem');
                $table->foreign('img_campanha')
                    ->references('id')
                    ->on('imagens_campanha')
                    ->onDelete('set null');
            }
        });
    }
};
