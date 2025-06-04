<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubPlotPlant2025 extends Model
{
    use HasFactory;
    protected $table = "im_spvptdata_2025";
    protected $connection = 'invasiflora';

    protected $fillable = [
        'plot_full_id',
        'spcode',
        'chname_index',
        'life_type',
        'coverage',
        'flowering',
        'fruiting',
        'note',
        'specimen_id',
        'cov_error',
        'unidentified',
        'created_by',
        'updated_by',
    ];
}