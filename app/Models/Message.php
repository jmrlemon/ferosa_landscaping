<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function attachmentIsImage(): bool
    {
        return $this->hasAttachment() && str_starts_with((string) $this->attachment_mime, 'image/');
    }

    /**
     * Attachments are on the private disk, so this points at the route that
     * checks who is asking rather than straight at a public file.
     */
    public function attachmentUrl(): ?string
    {
        return $this->hasAttachment()
            ? route('messages.attachment', $this)
            : null;
    }

    /**
     * Human-readable size, e.g. "820 KB". Shown next to document attachments so
     * people know what they are about to download.
     */
    public function attachmentSizeLabel(): ?string
    {
        if (! $this->attachment_size) {
            return null;
        }

        return $this->attachment_size >= 1048576
            ? round($this->attachment_size / 1048576, 1).' MB'
            : max(1, (int) round($this->attachment_size / 1024)).' KB';
    }

    /**
     * The shape both the customer chat and the admin inbox render from.
     */
    public function attachmentPayload(): ?array
    {
        if (! $this->hasAttachment()) {
            return null;
        }

        return [
            'url' => $this->attachmentUrl(),
            'name' => $this->attachment_name,
            'mime' => $this->attachment_mime,
            'is_image' => $this->attachmentIsImage(),
            'size_label' => $this->attachmentSizeLabel(),
        ];
    }
}
