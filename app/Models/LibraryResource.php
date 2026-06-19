<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryResource extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_id',
        'language',
        'slug',
        'title',
        'author',
        'publication_year',
        'description',
        'file_url',
        'resource_type',
        'resource_language',
        'access_right',
        'featured',
        'topics',
        'provinces',
        'featured_image',
        'source_url',
        'source_modified_at',
        'content_hash',
        'pdf_status',
        'pdf_text_hash',
        'pdf_indexed_at',
    ];

    /**
     * @return HasMany<LibraryChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(LibraryChunk::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'featured' => 'boolean',
            'publication_year' => 'integer',
            'topics' => 'array',
            'provinces' => 'array',
            'source_modified_at' => 'datetime',
            'pdf_indexed_at' => 'datetime',
        ];
    }
}
