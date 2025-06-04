<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubPlotPlant2010 extends Model
{
    use HasFactory;
    protected $table = "im_spvptdata_2010";
    protected $connection = 'invasiflora';
}