<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Assistant extends Model
{
    protected $fillable = [
        'user_id', 'name', 'description', 'icon', 'instructions',
        'model', 'temperature', 'max_tokens',
        'is_memory_active', 'is_favorite', 'status', 'allowed_tools',
    ];

    protected function casts(): array
    {
        return [
            'is_memory_active' => 'boolean',
            'is_favorite'      => 'boolean',
            'temperature'      => 'decimal:2',
            'allowed_tools'    => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function knowledgeCollections(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeCollection::class, 'assistant_knowledge_collection', 'assistant_id', 'collection_id');
    }
}
