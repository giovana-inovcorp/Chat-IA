<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCollection extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'collection_id');
    }

    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(Assistant::class, 'assistant_knowledge_collection', 'collection_id', 'assistant_id');
    }

    public function refreshStatus(): void
    {
        $statuses = $this->documents()->pluck('status');

        $status = match (true) {
            $statuses->isEmpty() => 'empty',
            $statuses->intersect(['pending', 'processing'])->isNotEmpty() => 'processing',
            $statuses->contains('error') => 'error',
            default => 'ready',
        };

        $this->update(['status' => $status]);
    }
}
