<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reasons extends Model
{

    protected $table = 'not_done_reasons';
    protected $connection = 'invasiflora';

}
