<?php

namespace Modules\TelegramBot\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TgbTopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [];

        // IELTS topics (migrated from app/Support/IeltsTopicCatalog).
        foreach (\App\Support\IeltsTopicCatalog::all() as $name => $data) {
            $slug = Str::slug('ielts-' . $name, '-');
            $topics[] = [
                'slug' => $this->limit($slug, 60),
                'purpose' => 'ielts',
                'name_vi' => $this->vietnamese($name),
                'name_en' => $name,
                'order_index' => 0, // filled below
                'difficulty' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Daily conversation topics.
        $daily = [
            ['Greetings & Introductions', 'Chào hỏi & Giới thiệu', 1],
            ['Daily Routines', 'Sinh hoạt hằng ngày', 1],
            ['Food & Dining', 'Đồ ăn & Nhà hàng', 2],
            ['Shopping & Money', 'Mua sắm & Tiền bạc', 2],
            ['Travel & Directions', 'Du lịch & Chỉ đường', 2],
            ['Weather & Seasons', 'Thời tiết & Mùa', 2],
            ['Health & Body', 'Sức khỏe & Cơ thể', 3],
            ['Hobbies & Free Time', 'Sở thích & Thời gian rảnh', 2],
            ['Feelings & Emotions', 'Cảm xúc', 3],
            ['Family & Relationships', 'Gia đình & Các mối quan hệ', 3],
        ];
        foreach ($daily as [$en, $vi, $diff]) {
            $topics[] = [
                'slug' => $this->limit(Str::slug('daily-' . $en), 60),
                'purpose' => 'daily',
                'name_vi' => $vi,
                'name_en' => $en,
                'order_index' => 0,
                'difficulty' => $diff,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Business English topics.
        $business = [
            ['Email & Correspondence', 'Email & Thư tín', 2],
            ['Meetings & Agendas', 'Họp & Chương trình', 3],
            ['Presentations', 'Thuyết trình', 3],
            ['Negotiation', 'Đàm phán', 4],
            ['Project Management', 'Quản lý dự án', 4],
            ['Customer Service', 'Chăm sóc khách hàng', 2],
            ['Phone Calls', 'Gọi điện thoại', 2],
            ['Networking', 'Giao lưu công việc', 3],
            ['Reports & Data', 'Báo cáo & Dữ liệu', 4],
            ['Interview Skills', 'Phỏng vấn', 3],
        ];
        foreach ($business as [$en, $vi, $diff]) {
            $topics[] = [
                'slug' => $this->limit(Str::slug('business-' . $en), 60),
                'purpose' => 'business',
                'name_vi' => $vi,
                'name_en' => $en,
                'order_index' => 0,
                'difficulty' => $diff,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Re-assign order_index within each purpose group.
        $byPurpose = [];
        foreach ($topics as $t) {
            $byPurpose[$t['purpose']][] = $t;
        }
        $final = [];
        foreach ($byPurpose as $rows) {
            foreach ($rows as $i => $t) {
                $t['order_index'] = $i + 1;
                $final[] = $t;
            }
        }

        // Upsert by slug.
        foreach ($final as $t) {
            DB::table('tgb_topics')->updateOrInsert(
                ['slug' => $t['slug']],
                $t,
            );
        }

        $this->command?->info('Seeded ' . count($final) . ' topics.');
    }

    private function limit(string $s, int $max): string
    {
        return strlen($s) > $max ? substr($s, 0, $max) : $s;
    }

    private function vietnamese(string $name): string
    {
        $map = [
            'Education' => 'Giáo dục',
            'Environment' => 'Môi trường',
            'Technology' => 'Công nghệ',
            'Health' => 'Sức khỏe',
            'Work & Career' => 'Công việc & Sự nghiệp',
            'Travel' => 'Du lịch',
            'Culture' => 'Văn hóa',
            'Social Media' => 'Mạng xã hội',
            'Government' => 'Chính phủ',
            'Crime' => 'Tội phạm',
            'Globalization' => 'Toàn cầu hóa',
            'Advertising' => 'Quảng cáo',
            'Family' => 'Gia đình',
            'Education Technology' => 'Công nghệ giáo dục',
            'Food' => 'Đồ ăn',
            'Housing' => 'Nhà ở',
            'Transport' => 'Giao thông',
            'Sports' => 'Thể thao',
            'Science' => 'Khoa học',
            'Happiness' => 'Hạnh phúc',
        ];

        return $map[$name] ?? $name;
    }
}
