<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationShareController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'visibility' => ['required', 'in:public,restricted'],
        ]);

        $conversation->share()->delete();

        $share = ConversationShare::create([
            'conversation_id' => $conversation->id,
            'token'           => Str::random(32),
            'visibility'      => $validated['visibility'],
        ]);

        return response()->json([
            'url'   => url('/share/' . $share->token),
            'share' => $share,
        ]);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $conversation->share()->delete();

        return response()->json(['message' => 'Partilha revogada.']);
    }

    public function show(string $token)
    {
        $share = ConversationShare::where('token', $token)->firstOrFail();

        if (!request()->wantsJson()) {
            return inertia('Share/Show');
        }

        if ($share->visibility === 'restricted' && !auth()->check()) {
            abort(403);
        }

        $conversation = $share->conversation()->with('messages')->first();

        return response()->json([
            'id'         => $conversation->id,
            'title'      => $conversation->title,
            'created_at' => $conversation->created_at,
            'messages'   => $conversation->messages,
            'visibility' => $share->visibility,
        ]);
    }
}
