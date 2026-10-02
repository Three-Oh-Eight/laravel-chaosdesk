<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use ThreeOhEight\ChaosDesk\Support\Identity;

/**
 * The person acting on a community board, as your application knows them.
 *
 * ChaosDesk keys the member on the external id within the site and keeps the
 * name and email up to date on every call. Eligibility is your call: only
 * build a member for a user you route to the board.
 */
final readonly class Member
{
    public function __construct(
        public string $externalId,
        public string $name,
        public string $email,
        public ?string $locale = null,
    ) {
        if ($externalId === '' || $name === '' || $email === '') {
            throw new InvalidArgumentException('A community member needs an external id, a name and an email address.');
        }
    }

    /**
     * Build the member from a user of your application.
     *
     * The external id comes from ProvidesSupportContext when implemented, the
     * auth identifier otherwise; name and email are read from the user. The
     * locale defaults to the user's `locale` attribute when it has one.
     *
     * @throws InvalidArgumentException when the user has no name or email
     */
    public static function fromUser(Authenticatable $user, ?string $locale = null): self
    {
        $externalId = Identity::externalId($user);
        $name = Identity::name($user);
        $email = Identity::email($user);

        if ($externalId === null || $name === null || $email === null) {
            throw new InvalidArgumentException(
                'A community member needs an external id, a name and an email address; the user is missing one of them.'
            );
        }

        $userLocale = data_get($user, 'locale');

        return new self(
            $externalId,
            $name,
            $email,
            $locale ?? (is_string($userLocale) && $userLocale !== '' ? $userLocale : null),
        );
    }

    /**
     * The X-Community-Member header value: base64 of the JSON identity.
     */
    public function header(): string
    {
        return base64_encode((string) json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @return array{external_id: string, name: string, email: string, locale?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'external_id' => $this->externalId,
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->locale !== null) {
            $payload['locale'] = $this->locale;
        }

        return $payload;
    }
}
