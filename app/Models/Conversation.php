<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'is_pinned',
        'assistant_id',
        'assistant_snapshot',
        'assistant_history',
    ];

    protected $hidden = ['assistant_snapshot', 'assistant_history'];

    protected function casts(): array
    {
        return [
            'assistant_snapshot' => 'array',
            'assistant_history'  => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function share(): HasOne
    {
        return $this->hasOne(ConversationShare::class);
    }
}