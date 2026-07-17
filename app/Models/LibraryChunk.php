<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryChunk extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'library_resource_id',
        'chunk_index',
        'content',
        'content_hash',
        'embedding',
    ];

    /**
     * @return BelongsTo<LibraryResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(LibraryResource::class, 'library_resource_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'embedding' => 'array',
        ];
    }
}
