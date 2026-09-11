<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'location',
        'service_name',
        'summary',
        'completed_at',
        'duration_label',
        'cover_image_path',
        'before_image_path',
        'after_image_path',
        'client_quote',
        'rating',
        'is_featured',
        'is_published',
    ];

    protected $casts = [
        'completed_at' => 'date',
        'rating' => 'integer',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
    ];

    protected $appends = [
        'cover_image_url',
        'before_image_url',
        'after_image_url',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->cover_image_path);
    }

    public function getBeforeImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->before_image_path);
    }

    public function getAfterImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->after_image_path);
    }

    private function publicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
