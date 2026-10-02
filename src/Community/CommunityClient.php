<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Community\Data\Values;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Exceptions\CommunityException;
use ThreeOhEight\ChaosDesk\Http\Concerns\TalksToChaosDesk;
use ThreeOhEight\ChaosDesk\Support\SiteConfig;

/**
 * Server-to-server client for the ChaosDesk Community API of one site.
 *
 * Authenticates with the site token, so it only ever runs on your server.
 * Obtain one via ChaosDesk::community() (or forSite($name)->community()); it
 * lists the site's boards. Everything on a board happens as a member: call
 * as() for a CommunityMemberClient.
 *
 * A refusal with a machine code (charter_not_accepted, member_blocked, ...)
 * throws CommunityException; any other failure the plain ChaosDeskException.
 */
class CommunityClient
{
    use TalksToChaosDesk;

    public function __construct(
        protected readonly string $site = SiteConfig::DEFAULT,
    ) {}

    /**
     * The configured site name this client talks to.
     */
    public function site(): string
    {
        return $this->site;
    }

    /**
     * A client acting as one member of your application.
     *
     * Pass a Member, or a user from which name, email and external id are
     * read (see Member::fromUser()). The locale only applies to a user.
     *
     * @throws InvalidArgumentException when the user has no name or email
     */
    public function as(Member|Authenticatable $member, ?string $locale = null): CommunityMemberClient
    {
        if ($member instanceof Authenticatable) {
            $member = Member::fromUser($member, $locale);
        }

        return new CommunityMemberClient($this->site, $member);
    }

    /**
     * The site's active boards, without charter or member state.
     *
     * Which of them a member may see is your call: route each member only to
     * the boards they are eligible for.
     *
     * @return list<Board>
     */
    public function boards(): array
    {
        $response = $this->get('public/community/boards');

        return array_map(Board::fromArray(...), Values::items($response, 'data'));
    }

    /**
     * The community API authenticates with the site token.
     *
     * @return array<string, string>
     */
    protected function authenticationHeaders(): array
    {
        $token = SiteConfig::token($this->site);

        if ($token === null) {
            throw ChaosDeskException::missingToken($this->site);
        }

        return ['X-Site-Token' => $token];
    }

    /**
     * A community refusal carries a machine code; surface it as such.
     *
     * @return array<string, mixed>
     */
    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw CommunityException::tryFromResponse($response) ?? ChaosDeskException::fromResponse($response);
        }

        return (array) $response->json();
    }
}
