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

    /**
     * Get filtered and paginated questions.
     */
    public function paginate(array $filters, int $perPage = 15)
    {
        return $this->repository->model()::query()->filter($filters)->paginate($perPage);
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
        return app(\App\Services\AI\GeminiService::class)->generateVoice($text);
    }
}
