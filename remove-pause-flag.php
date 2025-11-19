<?php
/**
 * Script para remover a flag de pausa travada
 * Use se a fila ficar travada com flag de pausa
 * 
 * php remove-pause-flag.php
 */

$flagFile = __DIR__ . '/storage/app/queue-pause.flag';

if (file_exists($flagFile)) {
    echo "🛑 Flag de pausa encontrada!\n";
    echo "   Arquivo: {$flagFile}\n";
    
    // Tenta remover
    if (unlink($flagFile)) {
        echo "✅ Flag removida com sucesso!\n";
        echo "   A fila agora pode processar normalmente.\n";
    } else {
        echo "❌ Erro ao remover a flag. Tente manualmente:\n";
        echo "   rm {$flagFile}\n";
    }
} else {
    echo "✅ Nenhuma flag de pausa encontrada.\n";
    echo "   A fila está funcionando normalmente.\n";
}

// Também mostra o status da fila
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n📊 Status da Fila:\n";
$jobs = DB::table('jobs')->count();
$contatoPendentes = DB::table('contato_dados')->where('send', 0)->count();
$contatoEnfileirados = DB::table('contato_dados')->where('send', 2)->count();

echo "   Jobs na fila: {$jobs}\n";
echo "   Contatos pendentes (send=0): {$contatoPendentes}\n";
echo "   Contatos enfileirados (send=2): {$contatoEnfileirados}\n";

if ($contatoEnfileirados > 0) {
    echo "\n⚠️  Há contatos travados em send=2!\n";
    echo "   Para limpar, execute:\n";
    echo "   php artisan tinker\n";
    echo "   > DB::table('contato_dados')->where('send', 2)->update(['send' => 0]);\n";
}
