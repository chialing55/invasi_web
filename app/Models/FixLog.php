<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class FixLog extends Model
{
    use HasFactory;

    protected $table = "fix_logs";
    protected $connection = 'invasiflora';

    public $timestamps = false;

    protected $fillable = [
        'table_name',
        'record_id',
        'changes',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'modified_at' => 'datetime',
    ];
}
