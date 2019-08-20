<?php
/*
 * Pterodactyl - Panel
 * Copyright (c) 2015 - 2017 Dane Everitt <dane@daneeveritt.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Services\DaemonKeys;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Server;
use App\Exceptions\Repository\RecordNotFoundException;
use App\Contracts\Repository\SubuserRepositoryInterface;
use App\Contracts\Repository\DaemonKeyRepositoryInterface;

class DaemonKeyProviderService
{
    /**
     * @var \App\Services\DaemonKeys\DaemonKeyCreationService
     */
    private $keyCreationService;

    /**
     * @var \App\Services\DaemonKeys\DaemonKeyUpdateService
     */
    private $keyUpdateService;

    /**
     * @var \App\Contracts\Repository\DaemonKeyRepositoryInterface
     */
    private $repository;

    /**
     * @var \App\Contracts\Repository\SubuserRepositoryInterface
     */
    private $subuserRepository;

    /**
     * GetDaemonKeyService constructor.
     *
     * @param \App\Services\DaemonKeys\DaemonKeyCreationService      $keyCreationService
     * @param \App\Contracts\Repository\DaemonKeyRepositoryInterface $repository
     * @param \App\Services\DaemonKeys\DaemonKeyUpdateService        $keyUpdateService
     * @param \App\Contracts\Repository\SubuserRepositoryInterface   $subuserRepository
     */
    public function __construct(
        DaemonKeyCreationService $keyCreationService,
        DaemonKeyRepositoryInterface $repository,
        DaemonKeyUpdateService $keyUpdateService,
        SubuserRepositoryInterface $subuserRepository
    ) {
        $this->keyCreationService = $keyCreationService;
        $this->keyUpdateService = $keyUpdateService;
        $this->repository = $repository;
        $this->subuserRepository = $subuserRepository;
    }

    /**
     * Get the access key for a user on a specific server.
     *
     * @param \App\Models\Server $server
     * @param \App\Models\User   $user
     * @param bool                       $updateIfExpired
     * @return string
     *
     * @throws \App\Exceptions\Model\DataValidationException
     * @throws \App\Exceptions\Repository\RecordNotFoundException
     */
    public function handle(Server $server, User $user, $updateIfExpired = true): string
    {
        try {
            $key = $this->repository->findFirstWhere([
                ['user_id', '=', $user->id],
                ['server_id', '=', $server->id],
            ]);
        } catch (RecordNotFoundException $exception) {
            // If key doesn't exist but we are an admin or the server owner,
            // create it.
            if ($user->root_admin || $user->id === $server->owner_id) {
                return $this->keyCreationService->handle($server->id, $user->id);
            }

            // Check if user is a subuser for this server. Ideally they should always have
            // a record associated with them in the database, but we should still handle
            // that potentiality here.
            //
            // If no subuser is found, a RecordNotFoundException will be thrown, thus handling
            // the parent error as well.
            $subuser = $this->subuserRepository->findFirstWhere([
                ['user_id', '=', $user->id],
                ['server_id', '=', $server->id],
            ]);

            return $this->keyCreationService->handle($subuser->server_id, $subuser->user_id);
        }

        if (! $updateIfExpired || Carbon::now()->diffInSeconds($key->expires_at, false) > 0) {
            return $key->secret;
        }

        return $this->keyUpdateService->handle($key->id);
    }
}
