<?php

namespace App\Notifications;

use App\Services\Telegram\TelegramBot;
use Illuminate\Notifications\Notification;

class PriceAlertNotification extends Notification
{
    public function __construct(
        public readonly string $message,
        public readonly float $price,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // إرسال تيليجرام مباشرة إن كان الحساب مربوطاً، والإشعار يُحفظ دائماً في الموقع
        if ($notifiable->telegram_chat_id) {
            app(TelegramBot::class)->send($notifiable->telegram_chat_id, '🔔 '.$this->message);
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'price' => $this->price];
    }
}
