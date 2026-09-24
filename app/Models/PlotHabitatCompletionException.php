<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotHabitatCompletionException extends Model
{
    protected $connection = 'invasiflora';

    protected $table = 'plot_habitat_completion_exceptions';

    protected $fillable = [
        'team',
        'county',
        'plot',
        'habitat_code',
        'actual_subplot_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'actual_subplot_count' => 'integer',
        ];
    }
}
