<?php

namespace App\Services\News;

use App\Models\NewsItem;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * يجلب الأخبار من خلاصات RSS التي يحددها المدير، ويحتفظ فقط بما يخص الاقتصاد والدولار.
 */
class NewsFetcher
{
    public const KEYWORDS = ['دولار', 'الدينار', 'البنك المركزي', 'نافذة', 'الصرف', 'العملة', 'الذهب', 'النفط', 'الرواتب', 'الموازنة', 'عقوبات', 'الفيدرالي', 'التضخم'];

    public function __construct(private readonly NewsAnalyzer $analyzer) {}

    public function fetch(): int
    {
        $count = 0;

        foreach ((array) Setting::get('news_feeds', []) as $feed) {
            try {
                $xml = new SimpleXMLElement(Http::timeout(15)->get($feed)->throw()->body());
            } catch (Throwable $e) {
                Log::warning("News feed {$feed} failed: {$e->getMessage()}");

                continue;
            }

            $source = (string) ($xml->channel->title ?? parse_url($feed, PHP_URL_HOST));

            foreach ($xml->channel->item ?? [] as $entry) {
                $title = trim((string) $entry->title);
                $body = trim(strip_tags((string) $entry->description));
                if (! $this->relevant($title.' '.$body)) {
                    continue;
                }

                $item = NewsItem::firstOrCreate(
                    ['url' => (string) $entry->link],
                    [
                        'title' => mb_substr($title, 0, 250),
                        'body' => $body,
                        'source_name' => $source,
                        'published_at' => rescue(fn () => Carbon::parse((string) $entry->pubDate), now(), false),
                    ],
                );

                if ($item->wasRecentlyCreated) {
                    $this->analyzer->analyze($item);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function relevant(string $text): bool
    {
        foreach (self::KEYWORDS as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
