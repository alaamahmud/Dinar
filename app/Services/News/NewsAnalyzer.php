<?php

namespace App\Services\News;

use Anthropic\Client;
use App\Models\NewsItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsAnalyzer
{
    public function __construct(private readonly RuleBasedAnalyzer $rules) {}

    public function aiAvailable(): bool
    {
        return (bool) Setting::get('ai_enabled') && filled(config('dinar.ai.api_key'));
    }

    public function analyze(NewsItem $item): NewsItem
    {
        $result = null;
        $by = 'rules';

        if ($this->aiAvailable()) {
            try {
                $analyzer = new ClaudeAnalyzer(new Client(apiKey: config('dinar.ai.api_key')));
                $result = $analyzer->analyze($item->title, $item->body);
                $by = 'ai';
            } catch (Throwable $e) {
                Log::warning('AI news analysis failed, falling back to rules: '.$e->getMessage());
            }
        }

        $result ??= $this->rules->analyze($item->title, $item->body);
        $item->update([...$result, 'analyzed_by' => $by]);

        return $item;
    }
}
