<?php
/**
 * Pterodactyl - Panel
 * Copyright (c) 2015 - 2017 Dane Everitt <dane@daneeveritt.com>.
 *
 * This software is licensed under the terms of the MIT license.
 * https://opensource.org/licenses/MIT
 */

namespace App\Events\Subuser;

use App\Models\Subuser;
use Illuminate\Queue\SerializesModels;

class Created
{
    use SerializesModels;

    /**
     * The Eloquent model of the server.
     *
     * @var \App\Models\Subuser
     */
    public $subuser;

    /**
     * Create a new event instance.
     *
     * @param \App\Models\Subuser $subuser
     */
    public function __construct(Subuser $subuser)
    {
        $this->subuser = $subuser;
    }
}
