<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeSegment extends Model
{
    protected $fillable = ['document_id', 'content', 'embedding', 'position'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }
}
