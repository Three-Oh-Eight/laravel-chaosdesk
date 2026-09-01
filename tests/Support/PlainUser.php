<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A host-application user that does not implement the support contract.
 */
class PlainUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
