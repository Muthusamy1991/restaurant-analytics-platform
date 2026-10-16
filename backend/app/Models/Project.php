<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'public_key',
        'server_key',
    ];

    protected $hidden = [
        'server_key',
    ];

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}