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
        'live_demo',
        'github_link',
    ];

    protected $casts = [
        'gallery'      => 'array',
        'technologies' => 'array',
    ];
}
