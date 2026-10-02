<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community;

use BackedEnum;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use ThreeOhEight\ChaosDesk\Community\Data\Board;
use ThreeOhEight\ChaosDesk\Community\Data\CharterAcceptance;
use ThreeOhEight\ChaosDesk\Community\Data\Page;
use ThreeOhEight\ChaosDesk\Community\Data\Poll;
use ThreeOhEight\ChaosDesk\Community\Data\PollOption;
use ThreeOhEight\ChaosDesk\Community\Data\PollResults;
use ThreeOhEight\ChaosDesk\Community\Data\Post;
use ThreeOhEight\ChaosDesk\Community\Data\Thread;
use ThreeOhEight\ChaosDesk\Community\Data\Values;
use ThreeOhEight\ChaosDesk\Community\Data\Vote;

/**
 * The Community API acting as one member of your application.
 *
 * Every call carries the site token and the member in the X-Community-Member
 * header. Boards are addressed by slug, threads and polls by ulid, and
 * ChaosDesk scopes every lookup to the board in the url: a thread from
 * another board answers 404 `thread_not_found`.
 *
 * Writes send an Idempotency-Key: pass your own to make a retry from your
 * side (a double-submitted form, a re-run job) replay the first response,
 * otherwise a fresh key per call covers the SDK's own retries. ChaosDesk
 * scopes the key to the member, so one key never replays another member's
 * write.
 */
final class CommunityMemberClient extends CommunityClient
{
    public const MEMBER_HEADER = 'X-Community-Member';

    public const LOCALE_HEADER = 'Accept-Language';

    public function __construct(string $site, private readonly Member $member)
    {
        parent::__construct($site);
    }

    /**
     * The member this client acts as.
     */
    public function member(): Member
    {
        return $this->member;
    }

    /**
     * A board with its charter, whether the member accepted the current
     * version, and the member's own state (blocked or not).
     */
    public function board(string $board): Board
    {
        return Board::fromArray($this->data($this->get($this->boardPath($board))));
    }

    /**
     * Accept the board's charter for the member.
     *
     * Pass the version the member was shown: when the charter changed in the
     * meantime ChaosDesk refuses with `charter_version_mismatch` (409) instead
     * of recording consent to text they never saw.
     */
    public function acceptCharter(string $board, ?int $version = null, ?string $idempotencyKey = null): CharterAcceptance
    {
        $response = $this->post(
            $this->boardPath($board, 'charter', 'accept'),
            $version === null ? [] : ['version' => $version],
            headers: $this->writeHeaders($idempotencyKey),
        );

        return CharterAcceptance::fromArray(Values::nested($response, 'charter') ?? []);
    }

    /**
     * The board's visible threads, pinned first.
     *
     * Filters: `kind` and `status` (an enum case or its value), `sort` one of
     * `activity` (default), `votes` or `newest`. `perPage` is at most 50.
     *
     * @param  array{kind?: ThreadKind|string|null, status?: ThreadStatus|string|null, sort?: string|null}  $filters
     * @return Page<Thread>
     */
    public function threads(string $board, array $filters = [], int $page = 1, int $perPage = 20): Page
    {
        $query = array_merge($this->filters($filters), ['page' => $page, 'per_page' => $perPage]);

        return Page::fromResponse(
            $this->get($this->boardPath($board, 'threads'), $query),
            Thread::fromArray(...),
        );
    }

    /**
     * One visible thread with its visible replies.
     */
    public function thread(string $board, string $thread): Thread
    {
        return Thread::fromArray($this->data($this->get($this->boardPath($board, 'threads', $thread))));
    }

    /**
     * Start a thread as the member.
     *
     * Refused with `charter_not_accepted` until the member accepted the
     * current charter, `member_blocked` for a blocked member; a kind the
     * board does not allow is a validation error.
     */
    public function createThread(
        string $board,
        ThreadKind|string $kind,
        string $title,
        string $body,
        ?string $idempotencyKey = null,
    ): Thread {
        $response = $this->post(
            $this->boardPath($board, 'threads'),
            [
                'kind' => $kind instanceof ThreadKind ? $kind->value : $kind,
                'title' => $title,
                'body' => $body,
            ],
            headers: $this->writeHeaders($idempotencyKey),
        );

        return Thread::fromArray(Values::nested($response, 'thread') ?? []);
    }

    /**
     * Reply to a thread as the member; a locked thread answers `thread_locked`.
     */
    public function reply(string $board, string $thread, string $body, ?string $idempotencyKey = null): Post
    {
        $response = $this->post(
            $this->boardPath($board, 'threads', $thread, 'posts'),
            ['body' => $body],
            headers: $this->writeHeaders($idempotencyKey),
        );

        return Post::fromArray(Values::nested($response, 'post') ?? []);
    }

    /**
     * Upvote a proposal. Voting twice is a no-op; anything but a proposal
     * answers `thread_not_votable`.
     */
    public function vote(string $board, string $thread, ?string $idempotencyKey = null): Vote
    {
        $response = $this->post(
            $this->boardPath($board, 'threads', $thread, 'vote'),
            [],
            headers: $this->writeHeaders($idempotencyKey),
        );

        return Vote::fromArray(Values::nested($response, 'vote') ?? []);
    }

    /**
     * Withdraw the member's upvote. Withdrawing twice is a no-op.
     */
    public function unvote(string $board, string $thread): Vote
    {
        $path = $this->boardPath($board, 'threads', $thread, 'vote');

        $response = $this->send(fn (PendingRequest $request): Response => $request->delete($this->url($path)));

        return Vote::fromArray(Values::nested($response, 'vote') ?? []);
    }

    /**
     * The board's polls that have opened, newest first, with the member's own
     * answer. Never the running tally: see pollResults().
     *
     * @return Page<Poll>
     */
    public function polls(string $board, int $page = 1, int $perPage = 20): Page
    {
        return Page::fromResponse(
            $this->get($this->boardPath($board, 'polls'), ['page' => $page, 'per_page' => $perPage]),
            Poll::fromArray(...),
        );
    }

    /**
     * Answer an open poll, replacing the member's earlier answer.
     *
     * A single-choice poll takes one option. A closed poll answers
     * `poll_closed`, one that has not opened yet `poll_not_open`.
     *
     * @param  list<PollOption|string>  $options  the picked options or their ulids
     */
    public function respond(string $board, string $poll, array $options, ?string $idempotencyKey = null): Poll
    {
        $ulids = array_values(array_map(
            fn (PollOption|string $option): string => $option instanceof PollOption ? $option->ulid : $option,
            $options,
        ));

        $response = $this->post(
            $this->boardPath($board, 'polls', $poll, 'responses'),
            ['options' => $ulids],
            headers: $this->writeHeaders($idempotencyKey),
        );

        return Poll::fromArray(Values::nested($response, 'poll') ?? []);
    }

    /**
     * The tally of a closed poll; `poll_results_hidden` (403) while it runs.
     */
    public function pollResults(string $board, string $poll): PollResults
    {
        return PollResults::fromArray($this->data($this->get($this->boardPath($board, 'polls', $poll, 'results'))));
    }

    /**
     * The site token, the acting member and the application's current locale.
     *
     * Built for every request, reads and writes alike, so ChaosDesk can serve
     * the board charter in the language the member is browsing in.
     *
     * @return array<string, string>
     */
    protected function authenticationHeaders(): array
    {
        return parent::authenticationHeaders() + [
            self::MEMBER_HEADER => $this->member->header(),
            self::LOCALE_HEADER => app()->getLocale(),
        ];
    }

    /**
     * The path below a board, every segment url-encoded so a slug or ulid can
     * never step outside the board it names.
     */
    private function boardPath(string $board, string ...$segments): string
    {
        $path = 'public/community/boards';

        foreach ([$board, ...$segments] as $segment) {
            if (trim($segment) === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException("A community board slug, thread or poll ulid may not be [{$segment}].");
            }

            $path .= '/'.rawurlencode($segment);
        }

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function writeHeaders(?string $idempotencyKey): array
    {
        return $idempotencyKey === null || $idempotencyKey === ''
            ? $this->idempotencyHeaders()
            : ['Idempotency-Key' => $idempotencyKey];
    }

    /**
     * Drop empty filters and turn enum cases into their values.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filters(array $filters): array
    {
        $query = [];

        foreach (['kind', 'status', 'sort'] as $key) {
            $value = $filters[$key] ?? null;

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function data(array $response): array
    {
        return Values::nested($response, 'data') ?? $response;
    }
}
