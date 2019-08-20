<?php

namespace App\Services\Users;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Auth\PasswordBroker;
use App\Notifications\AccountCreated;
use App\Contracts\Repository\UserRepositoryInterface;

class UserCreationService
{
    /**
     * @var \Illuminate\Database\ConnectionInterface
     */
    private $connection;

    /**
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    private $hasher;

    /**
     * @var \Illuminate\Contracts\Auth\PasswordBroker
     */
    private $passwordBroker;

    /**
     * @var \App\Contracts\Repository\UserRepositoryInterface
     */
    private $repository;

    /**
     * CreationService constructor.
     *
     * @param \Illuminate\Database\ConnectionInterface                  $connection
     * @param \Illuminate\Contracts\Hashing\Hasher                      $hasher
     * @param \Illuminate\Contracts\Auth\PasswordBroker                 $passwordBroker
     * @param \App\Contracts\Repository\UserRepositoryInterface $repository
     */
    public function __construct(
        ConnectionInterface $connection,
        Hasher $hasher,
        PasswordBroker $passwordBroker,
        UserRepositoryInterface $repository
    ) {
        $this->connection = $connection;
        $this->hasher = $hasher;
        $this->passwordBroker = $passwordBroker;
        $this->repository = $repository;
    }

    /**
     * Create a new user on the system.
     *
     * @param array $data
     * @return \App\Models\User
     *
     * @throws \Exception
     * @throws \App\Exceptions\Model\DataValidationException
     */
    public function handle(array $data)
    {
        if (array_key_exists('password', $data) && ! empty($data['password'])) {
            $data['password'] = $this->hasher->make($data['password']);
        }

        $this->connection->beginTransaction();
        if (! isset($data['password']) || empty($data['password'])) {
            $generateResetToken = true;
            $data['password'] = $this->hasher->make(Str::random(30));
        }

        /** @var \App\Models\User $user */
        $user = $this->repository->create(array_merge($data, [
            'uuid' => Uuid::uuid4()->toString(),
        ]), true, true);

        if (isset($generateResetToken)) {
            $token = $this->passwordBroker->createToken($user);
        }

        $this->connection->commit();
        $user->notify(new AccountCreated($user, $token ?? null));

        return $user;
    }
}
