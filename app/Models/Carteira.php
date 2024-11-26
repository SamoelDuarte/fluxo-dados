<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carteira extends Model
{
    use HasFactory;
    
    // Definir a tabela (não é obrigatório se o nome da tabela seguir a convenção)
    protected $table = 'carteiras';

    // Definir quais campos são atribuíveis em massa
    protected $fillable = ['codigo_usuario_cobranca'];

    // Definir a chave primária (caso não seja a padrão 'id')
    protected $primaryKey = 'id';

    // Como você está usando timestamps, se a tabela já tiver essas colunas, o Laravel irá tratá-las automaticamente
    public $timestamps = true;
}
