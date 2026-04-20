<?php

namespace Modules\Speaking\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Modules\Speaking\Models\Transcript;

class TranscriptRepositoryEloquent extends BaseRepository implements TranscriptRepositoryInterface
{
    public function model()
    {
        return Transcript::class;
    }
}
