<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Champion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_id',
        'language',
        'slug',
        'name',
        'summary',
        'story',
        'authors',
        'image_credits',
        'featured_image',
        'video_url',
        'file_url',
        'gallery',
        'category_actor',
        'province',
        'sectors',
        'topics',
        'scales',
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
            'gallery' => 'array',
            'sectors' => 'array',
            'topics' => 'array',
            'scales' => 'array',
            'source_modified_at' => 'datetime',
        ];
    }
}
