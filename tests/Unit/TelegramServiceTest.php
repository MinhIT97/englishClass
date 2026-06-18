<?php

namespace Tests\Unit;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_it_sends_an_html_safe_admin_alert(): void
    {
        config()->set('telegram.bot_token', 'test-token');
        config()->set('telegram.admin_chat_id', '123456');
        config()->set('telegram.base_url', 'https://api.telegram.org/bot');
        Cache::flush();

        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
        ]);

        $sent = app(TelegramService::class)->sendAdminAlert('AI <failed>', [
            'response' => '<script>bad</script>',
        ]);

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return $request['chat_id'] === '123456'
                && $request['parse_mode'] === 'HTML'
                && str_contains($request['text'], 'AI &lt;failed&gt;')
                && str_contains($request['text'], '&lt;script&gt;bad&lt;/script&gt;');
        });
    }

    public function test_it_throttles_duplicate_admin_alerts(): void
    {
        config()->set('telegram.bot_token', 'test-token');
        config()->set('telegram.admin_chat_id', '123456');
        config()->set('telegram.base_url', 'https://api.telegram.org/bot');
        Cache::flush();

        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ]),
        ]);

        $service = app(TelegramService::class);

        $this->assertTrue($service->sendAdminAlert('Repeated failure', ['status' => 500]));
        $this->assertFalse($service->sendAdminAlert('Repeated failure', ['status' => 500]));
        Http::assertSentCount(1);
    }
}
