<?php

namespace Tests\Feature\Analysis;

use App\Services\Analysis\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiBillingGuardTest extends TestCase
{
    private function service(): GeminiService
    {
        return app(GeminiService::class);
    }

    public function test_no_paid_gemini_call_is_made_when_ai_tester_active(): void
    {
        Http::fake();                       // any real call would be recorded
        session()->put('ai_tester', true);

        $svc = $this->service();
        $url = $svc->buildModelUrl('gemini-2.5-flash');

        $this->assertStringContainsString('AI TESTER STUB', $svc->executeWithCache('cachedContents/x', 'p', $url));
        $this->assertStringContainsString('AI TESTER STUB', $svc->executeRequest('p'));
        $this->assertStringContainsString('AI TESTER STUB', $svc->executeApiRequest(['contents' => []]));
        $this->assertStringContainsString('AI TESTER STUB', $svc->executeRequestWithModel('p', $url));

        $main = $svc->generateMainReportBlocks('ctx');
        $this->assertArrayHasKey('title', $main);
        $this->assertArrayHasKey('main', $main);

        $players = $svc->generatePlayerReportBlocks('ctx', ['Zavrikk']);
        $this->assertSame('ok', $players['Zavrikk']['status']);

        $this->assertNotNull($svc->createCachedContext('ctx', 'gemini-2.5-flash'));

        $this->assertTrue($svc->deleteCachedContext('cachedContents/ai-tester-stub'));

        Http::assertNothingSent();
    }

    public function test_calls_gemini_when_not_ai_tester(): void
    {
        Http::fake(['*' => Http::response(
            ['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]],
            200
        )]);

        $svc = $this->service();
        $svc->executeRequest('hello');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'generativelanguage.googleapis.com'));
    }
}
