<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ThreeOhEight\ChaosDesk\Contracts\ProvidesSupportContext;

/**
 * A host-application user that supplies its own support identity.
 */
class User extends Authenticatable implements ProvidesSupportContext
{
    protected $table = 'users';

    protected $guarded = [];

    public function supportExternalId(): string
    {
        return 'ext-'.$this->getKey();
    }

    public function supportContext(): array
    {
        return ['plan' => 'pro'];
    }
}
