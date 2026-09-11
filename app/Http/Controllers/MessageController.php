<?php

namespace App\Http\Controllers;

use App\Jobs\SendSmsJob;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->isStaffOrAdmin()) {
            return redirect()->route('admin.dashboard', ['tab' => 'messages']);
        }

        $conversation = Conversation::with(['messages' => fn ($q) => $q->with('sender:id,name,role')->oldest()])
            ->firstOrCreate(
                ['customer_id' => $user->id],
                ['last_message_at' => now()]
            );

        // Mark all admin messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Real opening hours set expectations better than a vague promise.
        $businessHours = AppSetting::getBusinessProfile()['business_hours'] ?? null;

        return view('messages', compact('conversation', 'businessHours'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Either half can stand alone: a caption with no file, or a photo with
        // no caption. Requiring both would block the common "here's a picture"
        // case, but an entirely empty message is still rejected.
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => MessageAttachment::rules(),
        ], [
            'body.required_without' => 'Type a message or attach a file.',
        ]);

        $user = auth()->user();
        abort_if($user->isStaffOrAdmin(), 403, 'Staff accounts must reply from the admin inbox.');

        $conversation = Conversation::firstOrCreate(
            ['customer_id' => $user->id],
            ['last_message_at' => now()]
        );

        $attachment = $request->hasFile('attachment')
            ? MessageAttachment::store($request->file('attachment'))
            : [];

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
            ...$attachment,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // SMS admin if this is their first unread message in this conversation
        $unreadCount = $conversation->messages()
            ->where('sender_id', $user->id)
            ->whereNull('read_at')
            ->count();

        if ($unreadCount === 1) {
            $admin = User::where('role', 'admin')->whereNotNull('phone_number')->first();
            if ($admin) {
                SendSmsJob::dispatch(
                    $admin->phone_number,
                    "Ferosa: {$user->name} sent you a new message. Reply on the admin dashboard."
                );
            }
        }

        // The chat sends over fetch(); the plain form post is kept as the
        // no-JavaScript fallback.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at->toISOString(),
                    'is_mine' => true,
                    'sender' => $user->name,
                    'attachment' => $message->attachmentPayload(),
                ],
            ], 201);
        }

        return back();
    }

    // JSON polling endpoint — returns messages created after a given timestamp
    public function poll(Request $request): JsonResponse
    {
        $user = auth()->user();
        abort_if($user->isStaffOrAdmin(), 403, 'Staff accounts must use the admin inbox.');

        $after = $request->query('after'); // ISO timestamp

        $conversation = Conversation::where('customer_id', $user->id)->first();
        if (! $conversation) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->with('sender:id,name,role')
            ->when($after, fn ($q) => $q->where('created_at', '>', $after))
            ->oldest()
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'created_at' => $m->created_at->toISOString(),
                'is_mine' => $m->sender_id === $user->id,
                'sender' => $m->sender->name,
                'attachment' => $m->attachmentPayload(),
            ]);

        // Mark newly-fetched admin messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Stream a chat attachment to the two parties entitled to see it.
     *
     * These files used to sit on the public disk, which meant a receipt or an
     * ID photo was retrievable by URL with no session at all. Access is now the
     * conversation's own customer, or staff answering it.
     */
    public function attachment(Request $request, Message $message): StreamedResponse
    {
        abort_unless($message->hasAttachment(), 404);

        $user = $request->user();
        $isOwner = (int) $message->conversation->customer_id === (int) $user->id;

        abort_unless($isOwner || $user->isStaffOrAdmin(), 403);

        $disk = MessageAttachment::diskFor($message->attachment_path);
        abort_unless($disk !== null, 404);

        // inline so images render in the thread; the stored name is only used
        // as the download filename, never as a filesystem path.
        return Storage::disk($disk)->response(
            $message->attachment_path,
            $message->attachment_name,
            ['Content-Type' => $message->attachment_mime ?: 'application/octet-stream']
        );
    }
}
