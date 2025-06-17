<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlotHab extends Model
{
    use HasFactory;
    protected $table = "plot_hab";
    protected $connection = 'invasiflora';

    protected $fillable = [
        'plot',
        'habitat_code',
        'note',
        'created_by'
    ];

    // 如果你有 created_at 和 updated_at 欄位，這個保持預設即可
    public $timestamps = true;

}