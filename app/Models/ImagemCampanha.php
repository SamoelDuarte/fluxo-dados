<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagemCampanha extends Model
{
    protected $table = 'imagens_campanha';

    protected $fillable = [
        'caminho_imagem',
    ];
}
