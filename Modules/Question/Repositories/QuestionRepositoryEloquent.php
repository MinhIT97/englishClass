<?php

namespace Modules\Question\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Modules\Question\Models\Question;

class QuestionRepositoryEloquent extends BaseRepository implements QuestionRepositoryInterface
{
    public function model()
    {
        return Question::class;
    }
}
