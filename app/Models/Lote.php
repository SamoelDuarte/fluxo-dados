<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    use HasFactory;
// Permitir atribuição em massa desses campos
protected $fillable = [
    'created_at',    // Adicione esse campo para resolver o problema
];
    // Definindo o relacionamento com as carteiras
    public function contratos()
    {
        return $this->hasMany(Contrato::class);
    }
    

    // // Definindo a relação entre Lote e Carteira
    // public function carteiras()
    // {
    //     return $this->hasMany(Carteira::class); // Relacionamento de "um para muitos" com Carteira
    // }
}
