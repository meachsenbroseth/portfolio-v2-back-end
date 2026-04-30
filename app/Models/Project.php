<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'date',
        'status',
        'desc',
        'image',
        'gallery',
        'technologies',
        'live_url',
        'github_url',
    ];

    protected $casts = [
        'gallery'      => 'array',
        'technologies' => 'array',
    ];
}
