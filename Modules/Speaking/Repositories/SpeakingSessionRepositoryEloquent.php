<?php

namespace Modules\Speaking\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Modules\Speaking\Models\SpeakingSession;

class SpeakingSessionRepositoryEloquent extends BaseRepository implements SpeakingSessionRepositoryInterface
{
    public function model()
    {
        return SpeakingSession::class;
    }
}
