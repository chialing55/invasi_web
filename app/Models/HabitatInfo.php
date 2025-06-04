<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HabitatInfo extends Model
{
    use HasFactory;
    protected $table = "habitat_info";
    protected $connection = 'invasiflora';
}