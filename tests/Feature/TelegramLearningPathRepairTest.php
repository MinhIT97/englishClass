<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TelegramBot\Models\LearningProfile;
use Modules\TelegramBot\Models\Topic;
use Modules\TelegramBot\Models\UserPath;
use Modules\TelegramBot\Services\TelegramLearningService;
use Tests\TestCase;

class TelegramLearningPathRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_a_missing_user_path_from_seeded_topics(): void
    {
        $user = User::factory()->create();
        $profile = LearningProfile::query()->create([
            'user_id' => $user->id,
            'purpose' => LearningProfile::PURPOSE_IELTS,
            'level' => LearningProfile::LEVEL_INTERMEDIATE,
            'daily_send_hour' => 7,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'onboarded_at' => now(),
        ]);

        $first = Topic::query()->create([
            'slug' => 'ielts-education',
            'purpose' => LearningProfile::PURPOSE_IELTS,
            'name_vi' => 'Giáo dục',
            'name_en' => 'Education',
            'order_index' => 1,
            'difficulty' => 2,
            'is_active' => true,
        ]);
        $second = Topic::query()->create([
            'slug' => 'ielts-technology',
            'purpose' => LearningProfile::PURPOSE_IELTS,
            'name_vi' => 'Công nghệ',
            'name_en' => 'Technology',
            'order_index' => 2,
            'difficulty' => 2,
            'is_active' => true,
        ]);

        $topic = app(TelegramLearningService::class)
            ->getOrAssignCurrentTopic($user, $profile);

        $this->assertTrue($topic->is($first));
        $this->assertDatabaseHas('tgb_user_paths', [
            'user_id' => $user->id,
            'topic_id' => $first->id,
            'status' => UserPath::STATUS_CURRENT,
        ]);
        $this->assertDatabaseHas('tgb_user_paths', [
            'user_id' => $user->id,
            'topic_id' => $second->id,
            'status' => UserPath::STATUS_LOCKED,
        ]);
    }
}
