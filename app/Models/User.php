<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'monthly_salary'])]
#[Hidden(['password', 'remember_token', 'api_token', 'display_token', 'telegram_link_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'trust_score' => 'float',
        ];
    }

    /**
     * الباقة الفعلية بعد التحقق من انتهاء الاشتراك.
     */
    public function activePlan(): string
    {
        if ($this->plan !== 'free' && $this->plan_expires_at !== null && $this->plan_expires_at->isPast()) {
            return 'free';
        }

        return $this->plan ?? 'free';
    }

    public function hasPlan(string $plan): bool
    {
        $rank = ['free' => 0, 'pro' => 1, 'trader' => 2];

        return ($rank[$this->activePlan()] ?? 0) >= ($rank[$plan] ?? 0);
    }

    public function planName(): string
    {
        return config('dinar.plans.'.$this->activePlan().'.name');
    }

    public function alertLimit(): ?int
    {
        return config('dinar.plans.'.$this->activePlan().'.alerts');
    }

    public function ensureApiToken(): string
    {
        if (! $this->api_token) {
            $this->forceFill(['api_token' => Str::random(60)])->save();
        }

        return $this->api_token;
    }

    public function ensureDisplayToken(): string
    {
        if (! $this->display_token) {
            $this->forceFill(['display_token' => Str::random(32)])->save();
        }

        return $this->display_token;
    }

    public function ensureTelegramLinkCode(): string
    {
        if (! $this->telegram_link_code) {
            $this->forceFill(['telegram_link_code' => strtoupper(Str::random(6))])->save();
        }

        return $this->telegram_link_code;
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function holdings(): HasMany
    {
        return $this->hasMany(Holding::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PriceReading::class);
    }
}
