<?php

namespace App\Services\News;

use Anthropic\Client;

/**
 * تحليل الأخبار بنموذج Claude: تلخيص في سطر + الأثر المتوقع على الدولار.
 * يُستخدم فقط عند تفعيله من لوحة الإدارة ووجود ANTHROPIC_API_KEY.
 */
class ClaudeAnalyzer
{
    private const SYSTEM = <<<'TXT'
    أنت محلل اقتصادي متخصص في سوق الصرف العراقي (سعر الدولار مقابل الدينار في السوق الموازي).
    لكل خبر: اكتب ملخصاً عربياً واضحاً في جملة واحدة لا تتجاوز 25 كلمة،
    وقدّر أثره المحتمل على سعر الدولار في السوق الموازي العراقي:
    - up: قد يرفع سعر الدولار (عقوبات، قيود على التحويلات، انخفاض مبيعات البنك المركزي، توتر سياسي...)
    - down: قد يخفض سعر الدولار (زيادة المعروض، تسهيلات، استقرار...)
    - neutral: أثر ضعيف أو غير مباشر
    وقوة الأثر من 0 إلى 3. كن حذراً ولا تبالغ؛ أغلب الأخبار أثرها محدود.
    TXT;

    public function __construct(private readonly Client $client) {}

    /**
     * @return array{summary: string, impact: string, impact_strength: int}
     */
    public function analyze(string $title, ?string $body = null): array
    {
        $message = $this->client->messages->create(
            model: config('dinar.ai.model'),
            maxTokens: 400,
            system: [
                ['type' => 'text', 'text' => self::SYSTEM, 'cacheControl' => ['type' => 'ephemeral']],
            ],
            messages: [
                ['role' => 'user', 'content' => "العنوان: {$title}\n\nالنص: ".mb_substr((string) $body, 0, 3000)],
            ],
            outputConfig: [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => ['type' => 'string'],
                            'impact' => ['type' => 'string', 'enum' => ['up', 'down', 'neutral']],
                            'impact_strength' => ['type' => 'integer'],
                        ],
                        'required' => ['summary', 'impact', 'impact_strength'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        );

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data) || ! isset($data['summary'], $data['impact'])) {
            throw new \RuntimeException('تعذر قراءة رد الذكاء الاصطناعي');
        }

        return [
            'summary' => (string) $data['summary'],
            'impact' => in_array($data['impact'], ['up', 'down', 'neutral'], true) ? $data['impact'] : 'neutral',
            'impact_strength' => max(0, min(3, (int) ($data['impact_strength'] ?? 0))),
        ];
    }
}
