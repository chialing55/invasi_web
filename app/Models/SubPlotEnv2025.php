<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubPlotEnv2025 extends Model
{
    use HasFactory;
    protected $table = "im_splotdata_2025";
    protected $connection = 'invasiflora';

    protected $fillable = [
        'team',
        'date',
        'year',
        'month',
        'day',
        'investigator',
        'recorder',
        'plot_full_id',
        'tm2_x',
        'tm2_y',
        'dd97_x',
        'dd97_y',
        'gps_error',
        'plot',
        'habitat_code',
        'subplot_id',
        'subplot_area',
        'island_category',
        'plot_env',
        'elevation',
        'slope',
        'aspect',
        'photo_id',
        'env_description',
        'note',
        'original_plot_id',
        'validation_message',
        'created_by',
        'updated_by',
    ];


}