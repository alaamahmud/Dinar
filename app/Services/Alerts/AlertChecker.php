<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Notifications\PriceAlertNotification;
use App\Services\Analytics\AnomalyDetector;
use App\Services\Pricing\PriceService;

class AlertChecker
{
    public function __construct(
        private readonly PriceService $prices,
        private readonly AnomalyDetector $anomalies,
    ) {}

    public function run(): int
    {
        $sent = 0;
        $anomaly = null;
        $anomalyChecked = false;

        Alert::with(['user', 'instrument', 'city'])->where('active', true)->get()->each(function (Alert $alert) use (&$sent, &$anomaly, &$anomalyChecked) {
            // لا نكرر التنبيه نفسه قبل 6 ساعات
            if ($alert->last_triggered_at && $alert->last_triggered_at->gt(now()->subHours(6))) {
                return;
            }

            $snapshot = $this->prices->latest($alert->instrument->code, $alert->city_id);
            if (! $snapshot) {
                return;
            }

            $message = null;
            if ($alert->condition === 'above' && $snapshot->mid >= $alert->threshold) {
                $message = sprintf('ارتفع %s إلى %s (حدّك: %s)', $alert->instrument->name_ar, number_format($snapshot->mid), number_format($alert->threshold));
            } elseif ($alert->condition === 'below' && $snapshot->mid <= $alert->threshold) {
                $message = sprintf('انخفض %s إلى %s (حدّك: %s)', $alert->instrument->name_ar, number_format($snapshot->mid), number_format($alert->threshold));
            } elseif ($alert->condition === 'spike') {
                if (! $anomalyChecked) {
                    $anomaly = $this->anomalies->detect();
                    $anomalyChecked = true;
                }
                $message = $anomaly['message'] ?? null;
            }

            if ($message) {
                $alert->user->notify(new PriceAlertNotification($message, $snapshot->mid));
                $alert->update(['last_triggered_at' => now()]);
                $sent++;
            }
        });

        return $sent;
    }
}
