<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContatoImport extends Model
{
    use HasFactory;

    protected $fillable = ['contato_id', 'file_path', 'total_rows', 'processed_rows', 'status', 'error'];

    public function contato()
    {
        return $this->belongsTo(Contato::class, 'contato_id');
    }
}
