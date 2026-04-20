<?php

namespace Modules\Auth\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface UserRepositoryInterface.
 *
 * @package namespace Modules\Auth\Repositories;
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email);
    public function updateStatus(int $id, string $status);
}
