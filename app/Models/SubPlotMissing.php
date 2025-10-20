<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubPlotMissing extends Model
{

    protected $table = 'sub_plot_missing';
    protected $connection = 'invasiflora';

    protected $fillable = [
        'team',
        'county',
        'plot',
        'plot_full_id_2010',
        'not_done_reason',
        'description',
        'created_by',
        'updated_by',
    ];

    public $timestamps = true;
}
