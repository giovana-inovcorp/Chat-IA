<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageCommentController extends Controller
{
    public function index(Message $message): JsonResponse
    {
        if ($message->conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $comments = $message->comments()
            ->orderBy('created_at')
            ->get(['id', 'body', 'created_at']);

        return response()->json($comments);
    }

    public function store(Request $request, Message $message): JsonResponse
    {
        if ($message->conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        $comment = $message->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $validated['body'],
        ]);

        return response()->json($comment, 201);
    }

    public function update(Request $request, MessageComment $comment): JsonResponse
    {
        if ($comment->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        $comment->update($validated);

        return response()->json($comment);
    }

    public function destroy(MessageComment $comment): JsonResponse
    {
        if ($comment->user_id !== auth()->id()) {
            abort(403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comentário apagado.']);
    }
}
