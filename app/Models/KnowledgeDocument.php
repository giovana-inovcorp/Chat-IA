<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    protected $fillable = ['collection_id', 'filename', 'original_name', 'mime_type', 'status', 'error_message'];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCollection::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(KnowledgeSegment::class, 'document_id');
    }
}
