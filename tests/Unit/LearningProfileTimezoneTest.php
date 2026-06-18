<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\TelegramBot\Models\LearningProfile;
use PHPUnit\Framework\TestCase;

class LearningProfileTimezoneTest extends TestCase
{
    public function test_it_matches_daily_send_hour_in_the_profile_timezone(): void
    {
        $profile = new LearningProfile([
            'daily_send_hour' => 7,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $now = Carbon::create(2026, 6, 18, 0, 0, 0, 'UTC');

        $this->assertTrue($profile->isDailySendTime($now));
        $this->assertSame('2026-06-18 07:00:00', $profile->localDateTime($now)->toDateTimeString());
        $this->assertSame('UTC', $now->timezoneName);
    }

    public function test_it_does_not_match_a_different_local_hour(): void
    {
        $profile = new LearningProfile([
            'daily_send_hour' => 7,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $now = Carbon::create(2026, 6, 18, 3, 53, 32, 'UTC');

        $this->assertFalse($profile->isDailySendTime($now));
        $this->assertSame('2026-06-18 10:53:32', $profile->localDateTime($now)->toDateTimeString());
    }

    public function test_it_falls_back_to_the_default_timezone_when_profile_timezone_is_invalid(): void
    {
        $profile = new LearningProfile([
            'daily_send_hour' => 7,
            'timezone' => 'invalid/timezone',
        ]);

        $now = Carbon::create(2026, 6, 18, 0, 0, 0, 'UTC');

        $this->assertTrue($profile->isDailySendTime($now));
        $this->assertSame(LearningProfile::DEFAULT_TIMEZONE, $profile->localDateTime($now)->timezoneName);
    }
}
