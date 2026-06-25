<?php

namespace Modules\Question\Services;

use Modules\Question\Repositories\QuestionRepositoryInterface;

class QuestionService
{
    protected $repository;

    public function __construct(QuestionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    private const MAX_PER_PAGE = 100;

    /**
     * Get filtered and paginated questions.
     *
     * SECURITY (SEC-013): $perPage is capped at MAX_PER_PAGE to prevent
     * a user-supplied ?limit=99999999 from causing unbounded database
     * result sets and DoS. Follows the same pattern as CourseService.
     */
    public function paginate(array $filters, int $perPage = 15)
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        return $this->repository->models()::query()->filter($filters)->paginate($perPage);
    }

    public function create(array $data)
    {
        return $this->repository->create($data);
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function update(int $id, array $data)
    {
        return $this->repository->update($data, $id);
    }

    public function delete(int $id)
    {
        return $this->repository->delete($id);
    }

    public function generateVoice(string $text)
    {
        return app(\Modules\Speaking\Services\AiSpeakingService::class)->generateTTS($text);
    }
}
