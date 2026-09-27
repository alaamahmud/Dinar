<?php

namespace App\Services\Pricing\Sources;

use App\Models\City;
use App\Models\Instrument;
use App\Models\PriceReading;
use App\Models\Source;
use App\Services\Pricing\TextPriceParser;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * يقرأ آخر منشورات قناة تيليجرام عامة من صفحة المعاينة العامة (t.me/s/اسم_القناة)
 * — لا يحتاج حساباً ولا بوتاً — ثم يستخرج الأسعار من النص.
 */
class TelegramChannelSource implements PriceSource
{
    public function __construct(private readonly TextPriceParser $parser) {}

    public function fetch(Source $source): int
    {
        $channel = $source->config['channel'] ?? null;
        if (! $channel) {
            throw new RuntimeException('لم يُحدد اسم القناة في إعدادات المصدر');
        }

        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DinarPulse/1.0)'])
            ->get("https://t.me/s/{$channel}");
        $response->throw();

        return $this->ingest($source, $this->extractMessages($response->body()));
    }

    /**
     * @return list<array{id: string, text: string, time: ?string}>
     */
    public function extractMessages(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $messages = [];
        foreach ($xpath->query('//div[contains(@class,"tgme_widget_message ") and @data-post]') as $node) {
            $textNode = $xpath->query('.//div[contains(@class,"tgme_widget_message_text")]', $node)->item(0);
            if ($textNode === null) {
                continue;
            }
            // نحافظ على الأسطر
            $text = '';
            foreach ($textNode->childNodes as $child) {
                $text .= $child->nodeName === 'br' ? "\n" : $child->textContent;
            }
            $time = $xpath->query('.//time[@datetime]', $node)->item(0)?->getAttribute('datetime');

            $messages[] = [
                'id' => $node->getAttribute('data-post'),
                'text' => trim($text),
                'time' => $time,
            ];
        }

        return $messages;
    }

    /**
     * @param  list<array{id: string, text: string, time: ?string}>  $messages
     */
    public function ingest(Source $source, array $messages): int
    {
        $usd = Instrument::byCode('usd');
        $cities = City::pluck('id', 'slug');
        $defaultCity = $source->config['default_city'] ?? 'baghdad-kifah';
        $count = 0;

        foreach ($messages as $message) {
            $time = $message['time'] ? Carbon::parse($message['time'])->setTimezone(config('app.timezone')) : now();
            if ($time->lt(now()->subHours(3))) {
                continue; // قديم جداً
            }

            foreach ($this->parser->parse($message['text']) as $i => $price) {
                $cityId = $cities[$price['city'] ?? $defaultCity] ?? null;
                $reading = PriceReading::firstOrCreate(
                    ['source_id' => $source->id, 'external_id' => $message['id'].'#'.$i],
                    [
                        'instrument_id' => $usd->id,
                        'city_id' => $cityId,
                        'buy' => $price['buy'],
                        'sell' => $price['sell'],
                        'note_type' => $price['note_type'],
                        'recorded_at' => $time,
                        'meta' => ['excerpt' => mb_substr($message['text'], 0, 200)],
                    ],
                );
                $count += $reading->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $count;
    }
}
