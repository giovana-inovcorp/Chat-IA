<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolExecution extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'tool',
        'parameters',
        'result_summary',
        'status',
        'error_message',
        'duration_ms',
        'context',
    ];

    protected $casts = [
        'parameters' => 'array',
        'context'    => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
