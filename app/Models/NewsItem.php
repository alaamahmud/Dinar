<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsItem extends Model
{
    protected $guarded = [];

    public const IMPACTS = [
        'up' => ['label' => 'قد يرفع الدولار', 'icon' => '▲', 'class' => 'text-down'],
        'down' => ['label' => 'قد يخفض الدولار', 'icon' => '▼', 'class' => 'text-up'],
        'neutral' => ['label' => 'أثر محدود', 'icon' => '●', 'class' => 'text-muted'],
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function impactLabel(): string
    {
        return self::IMPACTS[$this->impact]['label'] ?? '';
    }

    public function impactIcon(): string
    {
        return self::IMPACTS[$this->impact]['icon'] ?? '';
    }

    public function impactClass(): string
    {
        return self::IMPACTS[$this->impact]['class'] ?? '';
    }
}
