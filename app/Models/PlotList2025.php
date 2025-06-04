<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlotList2025 extends Model
{
    use HasFactory;
    protected $table = "plot_list";
    protected $connection = 'invasiflora';
}