<?php

namespace App\Http\Controllers\Admin;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminContactMessageController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $messages = ContactMessage::with('user')
            ->latest()
            ->get()
            ->map(fn (ContactMessage $message) => [
                'id' => $message->id,
                'user_name' => $message->user?->name,
                'name' => $message->name,
                'email' => $message->email,
                'category' => $message->category,
                'order_number' => $message->order_number,
                'message' => $message->message,
                'status' => $message->status,
                'admin_note' => $message->admin_note,
                'created_at' => $message->created_at?->toISOString(),
            ])
            ->values();

        return response()->json(['contact_messages' => $messages]);
    }

    public function update(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        if (! $this->authenticatedAdmin($request)) {
            return response()->json(['message' => '管理者認証が必要です。'], 401);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,in_progress,resolved'],
        ]);

        $contactMessage->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'contact_message' => [
                'id' => $contactMessage->id,
                'user_name' => $contactMessage->user?->name,
                'name' => $contactMessage->name,
                'email' => $contactMessage->email,
                'category' => $contactMessage->category,
                'order_number' => $contactMessage->order_number,
                'message' => $contactMessage->message,
                'status' => $contactMessage->status,
                'admin_note' => $contactMessage->admin_note,
                'created_at' => $contactMessage->created_at?->toISOString(),
            ],
        ]);
    }
}
