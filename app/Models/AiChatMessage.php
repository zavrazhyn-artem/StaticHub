<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $tactical_report_id
 * @property int    $user_id
 * @property string $role    'user' | 'assistant'
 * @property string $content  user: plain text; assistant: JSON blocks
 * @property \Illuminate\Support\Carbon $created_at
 */
class AiChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tactical_report_id',
        'user_id',
        'role',
        'content',
    ];

    protected static function booted(): void
    {
        static::creating(fn($model) => $model->created_at ??= now());
    }

    public function tacticalReport(): BelongsTo
    {
        return $this->belongsTo(TacticalReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Decode assistant content to blocks array.
     * Returns null for user messages or malformed JSON.
     */
    public function getBlocks(): ?array
    {
        if ($this->role !== 'assistant') return null;

        $data = json_decode($this->content, true);
        return $data['blocks'] ?? null;
    }

    /**
     * Extract plain text from blocks for history context sent to AI.
     * Compact representation — no HTML, no structure.
     */
    public function toHistoryText(): string
    {
        if ($this->role === 'user') return $this->content;

        $data = json_decode($this->content, true);
        if (!$data || !isset($data['blocks'])) return $this->content;

        $parts = [];
        foreach ($data['blocks'] as $block) {
            $parts[] = match ($block['type'] ?? '') {
                'text', 'alert', 'directive' => $block['content'] ?? '',
                'list'     => implode(', ', $block['items'] ?? []),
                'metric'   => ($block['label'] ?? '') . ': ' . ($block['value'] ?? ''),
                default    => '',
            };
        }

        return implode(' ', array_filter($parts));
    }
}
