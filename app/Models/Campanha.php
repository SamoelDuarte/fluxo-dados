<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campanha extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status', 'mensagem'];

    public function contatos()
    {
        return $this->belongsToMany(Contato::class, 'campanha_contato', 'campanha_id', 'contato_id');
    }

    public function telefones()
    {
        return $this->belongsToMany(Telefone::class, 'campanha_telefone', 'campanha_id', 'telefone_id');
    }
}
