<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubPlotEnv2010 extends Model
{
    use HasFactory;
    protected $table = "im_splotdata_2010";
    protected $connection = 'invasiflora';
}