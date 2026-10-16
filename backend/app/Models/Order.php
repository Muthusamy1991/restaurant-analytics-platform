<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'project_id',
        'order_number',
        'status',
        'items',
        'item_count',
        'total_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'item_count' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
