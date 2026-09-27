<?php

namespace App\Services\Analytics;

use App\Models\PriceReading;
use App\Models\PriceSnapshot;
use App\Models\Source;
use App\Models\User;

/**
 * يقيس دقة كل مصدر (قناة/مستخدم) بمقارنة قراءاته بالسعر النهائي المحسوب.
 */
class SourceAccuracy
{
    public function refresh(int $days = 30): void
    {
        foreach (Source::all() as $source) {
            $query = PriceReading::where('source_id', $source->id);
            if ($source->type !== 'crowd') {
                $query->whereNull('user_id');
            }
            $errors = $this->errorsFor($query, $days);
            $source->forceFill(['accuracy' => $errors ? round(Stats::mean($errors), 3) : null])->save();
        }

        // ثقة المبلّغين: كلما اقتربت بلاغاتهم من السعر الحقيقي ارتفعت
        foreach (User::whereHas('reports')->get() as $user) {
            $errors = $this->errorsFor(PriceReading::where('user_id', $user->id), $days);
            if (count($errors) >= 3) {
                $avgError = Stats::mean($errors); // بالنسبة المئوية
                $user->forceFill(['trust_score' => round(Stats::clamp(1 - $avgError / 2, 0.05, 1), 3)])->save();
            }
        }
    }

    /**
     * @return list<float> الانحراف % لكل قراءة
     */
    private function errorsFor($query, int $days): array
    {
        $errors = [];
        $query->where('recorded_at', '>=', now()->subDays($days))
            ->orderByDesc('recorded_at')
            ->limit(300)
            ->get()
            ->each(function (PriceReading $reading) use (&$errors) {
                $snapshot = PriceSnapshot::where('instrument_id', $reading->instrument_id)
                    ->where('city_id', $reading->city_id)
                    ->where('note_type', $reading->note_type)
                    ->whereBetween('computed_at', [$reading->recorded_at, $reading->recorded_at->copy()->addHour()])
                    ->orderBy('computed_at')
                    ->first();
                if ($snapshot && $snapshot->mid > 0 && $reading->mid() > 0) {
                    $errors[] = abs($reading->mid() - $snapshot->mid) / $snapshot->mid * 100;
                }
            });

        return $errors;
    }
}
