<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaiwanChecklist extends Model
{
    use HasFactory;

    protected $connection = 'invasiflora';
    protected $table = 'taiwan_checklist';
}
