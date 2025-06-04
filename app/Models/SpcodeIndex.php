<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpcodeIndex extends Model
{
    use HasFactory;
    protected $table = "spcode_index";
    protected $connection = 'invasiflora';

    protected $fillable = [
        'spcode',
        'chname_index',
        'note',
        'created_by',
        'updated_by',
    ];

    // 如果你有 created_at 和 updated_at 欄位，這個保持預設即可
    public $timestamps = true;

}