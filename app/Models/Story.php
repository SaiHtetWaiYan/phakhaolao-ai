<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_id',
        'language',
        'slug',
        'title',
        'authors',
        'summary',
        'story',
        'image_credits',
        'story_types',
        'source_url',
        'source_modified_at',
        'content_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'story_types' => 'array',
            'source_modified_at' => 'datetime',
        ];
    }
}
