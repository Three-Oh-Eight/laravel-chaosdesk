<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Contracts;

/**
 * Implement on your User model so tickets carry a stable identity.
 *
 * ChaosDesk keys the ticket author on the external id, which means the same
 * person stays a single author even after they change their email address.
 */
interface ProvidesSupportContext
{
    /**
     * A stable identifier for this user within your application.
     */
    public function supportExternalId(): string;

    /**
     * Extra user attributes to attach, for example a plan name.
     *
     * Recognised keys: plan, signed_up_at. Anything else is ignored.
     *
     * @return array<string, string|null>
     */
    public function supportContext(): array;
}
