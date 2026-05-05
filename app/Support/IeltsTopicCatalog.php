<?php

namespace App\Support;

class IeltsTopicCatalog
{
    public static function all(): array
    {
        return [
            'Education' => [
                'vocabulary' => ['curriculum', 'tuition fees', 'academic performance'],
                'questions' => ['Do you enjoy studying?', 'Should university be free?', 'Is the education system outdated?'],
                'writing_task' => 'Some believe education should be free. Discuss both views.',
            ],
            'Environment' => [
                'vocabulary' => ['pollution', 'climate change', 'sustainability'],
                'questions' => ['Is pollution a problem in your city?', 'How can individuals protect the environment?', 'Should governments impose stricter laws?'],
                'writing_task' => 'Environmental problems are too big for individuals to solve. Agree or disagree?',
            ],
            'Technology' => [
                'vocabulary' => ['artificial intelligence', 'automation', 'digital life'],
                'questions' => ['Do you use technology every day?', 'Does technology make life easier?', 'Will AI replace human jobs?'],
                'writing_task' => 'Technology is making people less social. Discuss.',
            ],
            'Health' => [
                'vocabulary' => ['balanced diet', 'mental health', 'healthcare'],
                'questions' => ['Do you exercise regularly?', 'Should governments promote healthy lifestyles?', 'Is mental health more important than physical health?'],
                'writing_task' => 'Prevention is better than cure. Discuss.',
            ],
            'Work & Career' => [
                'vocabulary' => ['job satisfaction', 'salary', 'work-life balance'],
                'questions' => ['What job would you like to do?', 'Is salary the most important factor?', 'Should people change jobs often?'],
                'writing_task' => 'Job satisfaction is more important than salary. Agree?',
            ],
            'Travel' => [
                'vocabulary' => ['tourism', 'destination', 'cultural experience'],
                'questions' => ['Do you like traveling?', 'What are the benefits of tourism?', 'Does tourism harm local culture?'],
                'writing_task' => 'Tourism brings more harm than good. Discuss.',
            ],
            'Culture' => [
                'vocabulary' => ['tradition', 'customs', 'heritage'],
                'questions' => ['Do you follow traditions?', 'Should traditions be preserved?', 'Is globalization harming culture?'],
                'writing_task' => 'Traditional culture is disappearing. What can be done?',
            ],
            'Social Media' => [
                'vocabulary' => ['online platforms', 'addiction', 'communication'],
                'questions' => ['Do you use social media?', 'Is it a waste of time?', 'Does it affect mental health?'],
                'writing_task' => 'Social media does more harm than good. Discuss.',
            ],
            'Government' => [
                'vocabulary' => ['policy', 'public services', 'taxation'],
                'questions' => ['Should the government provide free education?', 'What is the role of government?', 'Should taxes be increased?'],
                'writing_task' => 'Governments should spend more on public services.',
            ],
            'Crime' => [
                'vocabulary' => ['criminal', 'punishment', 'law enforcement'],
                'questions' => ['Is crime increasing?', 'Why do people commit crimes?', 'Is prison effective?'],
                'writing_task' => 'Harsh punishment reduces crime. Agree?',
            ],
            'Globalization' => [
                'vocabulary' => ['global trade', 'economy', 'integration'],
                'questions' => ['Is globalization positive?', 'Does it affect local jobs?', 'Should countries be more independent?'],
                'writing_task' => 'Globalization benefits everyone. Discuss.',
            ],
            'Advertising' => [
                'vocabulary' => ['marketing', 'consumer behavior', 'brand'],
                'questions' => ['Are ads useful?', 'Do ads influence people?', 'Should ads be controlled?'],
                'writing_task' => 'Advertising encourages overconsumption.',
            ],
            'Family' => [
                'vocabulary' => ['generation gap', 'parenting', 'relationships'],
                'questions' => ['Are families closer now?', 'Should children obey parents?', 'Is family influence important?'],
                'writing_task' => 'Parents are the best teachers. Discuss.',
            ],
            'Education Technology' => [
                'vocabulary' => ['online learning', 'e-learning', 'digital tools'],
                'questions' => ['Is online learning effective?', 'Should schools use more tech?', 'Will classrooms disappear?'],
                'writing_task' => 'Online learning is better than traditional learning.',
            ],
            'Food' => [
                'vocabulary' => ['fast food', 'nutrition', 'diet'],
                'questions' => ['Do you eat healthy?', 'Is fast food harmful?', 'Should junk food be banned?'],
                'writing_task' => 'Governments should tax unhealthy food.',
            ],
            'Housing' => [
                'vocabulary' => ['urbanization', 'accommodation', 'housing prices'],
                'questions' => ['Is housing expensive?', 'Should government support housing?', 'Is city life better than countryside?'],
                'writing_task' => 'Cities are better than rural areas.',
            ],
            'Transport' => [
                'vocabulary' => ['traffic', 'public transport', 'infrastructure'],
                'questions' => ['Is traffic a problem?', 'Should people use public transport?', 'How to reduce congestion?'],
                'writing_task' => 'Public transport should be free.',
            ],
            'Sports' => [
                'vocabulary' => ['physical activity', 'competition', 'teamwork'],
                'questions' => ['Do you play sports?', 'Are sports important?', 'Should sports be mandatory?'],
                'writing_task' => 'Sports are essential for education.',
            ],
            'Science' => [
                'vocabulary' => ['research', 'innovation', 'discovery'],
                'questions' => ['Is science important?', 'Should governments fund research?', 'Is science always beneficial?'],
                'writing_task' => 'Science does more good than harm.',
            ],
            'Happiness' => [
                'vocabulary' => ['satisfaction', 'well-being', 'success'],
                'questions' => ['What makes people happy?', 'Is money important for happiness?', 'Can happiness be measured?'],
                'writing_task' => 'Money cannot buy happiness. Discuss.',
            ],
        ];
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $topic): ?array
    {
        return self::all()[$topic] ?? null;
    }
}
