# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-10-02

### Added

- `Community\CommunityMemberClient` sends `Accept-Language` with the application's current locale (`app()->getLocale()`) on every member call, reads and writes alike, so ChaosDesk can serve a board's charter in the member's language.
- `Community\Data\Board::$charterLocale` (`?string`) and `Community\Data\Charter::$locale` (`?string`): the language the charter was served in, read from `charter.locale` and null when the API reports none (an older ChaosDesk, or the default body). Both are trailing optional constructor parameters, so existing positional callers keep working; `Charter::toArray()` only includes `locale` when it is set.

### Changed

- `ChaosDesk::VERSION` is `1.3.0`, reported in `context.sdk` and the `User-Agent`.

## [1.2.0] - 2026-10-02

### Added

- Community client for the ChaosDesk Community API: `ChaosDesk::community()` (and `forSite($name)->community()`) returns a `Community\CommunityClient` that lists the site's active boards with `boards()`. `as($member)` returns a `Community\CommunityMemberClient` acting as one member: `board()`, `acceptCharter()`, `threads()`, `thread()`, `createThread()`, `reply()`, `vote()`, `unvote()`, `polls()`, `respond()` and `pollResults()`.
- `Community\Member` (external id, name, email, optional locale), built directly or with `Member::fromUser()` from the host's user through `Support\Identity`. `as()` accepts either. The member travels base64 JSON encoded in the `X-Community-Member` header next to the site token.
- Readonly data objects under `Community\Data`: `Board`, `Charter`, `MemberStatus`, `CharterAcceptance`, `Thread`, `Post`, `Vote`, `Poll`, `PollOption`, `PollResults` and the paginated `Page`, each with `fromArray()` and `toArray()`. Enums `Community\ThreadKind` and `Community\ThreadStatus` for filters and comparisons.
- Every community write sends an `Idempotency-Key`: the one you pass, or a fresh one per call.
- `Exceptions\CommunityException` (extends `ChaosDeskException`) for refusals that carry a machine code, exposed as `errorCode` with constants for `charter_not_accepted`, `charter_version_mismatch`, `member_blocked`, `thread_locked`, `thread_not_votable`, `poll_closed`, `poll_not_open`, `poll_results_hidden`, `member_identity_conflict` and the `*_not_found` codes, plus `is()`, `requiresCharterAcceptance()`, `isMemberBlocked()`, `isThreadLocked()` and `isPollUnavailable()`.
- `Webhooks\WebhookEvent` with constants for every event ChaosDesk sends, including the community events `community.thread.status_changed`, `community.post.created`, `community.poll.opened` and `community.poll.closed`, the `X-ChaosDesk-*` header names, and `ticket()`, `community()`, `all()` and `isCommunity()`.
- Livewire community components, styled with plain Tailwind: `Livewire\CommunityBoard` (`chaosdesk-community-board`: threads with kind and status filters, sorting, paging, upvotes and an open polls summary), `Livewire\CommunityThread` (`chaosdesk-community-thread`: a thread with replies and a reply form), `Livewire\CommunityNewThread` (`chaosdesk-community-new-thread`), `Livewire\CommunityPolls` (`chaosdesk-community-polls`: answer open polls, results of closed ones) and `Livewire\CommunityCharter` (`chaosdesk-community-charter`). Each takes the locked props `board`, `site` and `embedded` (`thread` for the thread component), acts as the signed-in user, renders the charter until the member accepted the current version, and shows refusals as translated messages. They dispatch `chaosdesk-community-charter-accepted` and `chaosdesk-community-thread-created`. A thread or poll id from the browser that is not a ulid never reaches the API, and the thread component answers 404 for a `thread` that is not one.
- `chaosdesk.components.community_*` for the component names, `chaosdesk.community.routes.{board,thread,new_thread,polls}` for the route names the components link to (a page without a route opens inline inside the board) and `chaosdesk.community.per_page`.
- `Community\CommunityUrls` resolves those links; `CommunityUrls::resolveUsing()` registers a callback for anything a route name cannot express.
- `Support\CommunityText`: translated labels for kinds, statuses and sorts (a headline of the raw value when there is no label), member-facing messages for refusal codes, and charter markdown rendered with raw HTML escaped and unsafe links dropped.
- `resources/lang/{en,nl,fr,de}/community.php` with every string the community components render, under `chaosdesk::community`, published with `--tag=chaosdesk-lang`; the community views publish with `--tag=chaosdesk-views`.
- Test helpers `fakeChaosDeskCommunity()`, `fakeCommunityMember()`, `communityUser()`, `communityRequests()` and the community payload builders in `tests/Pest.php`.
- `agent()` and `community()` on the facade docblock.

## [1.1.0] - 2026-09-11

### Added

- Several sites: `chaosdesk.sites.{name}` with `token`, `agent_token` and `site_id` per site, `ChaosDesk::forSite()` and `ChaosDesk::site()`, and `Support\SiteConfig` to read the per-site credentials. The `default` site falls back to `CHAOSDESK_SITE_TOKEN` and to the new single-site `chaosdesk.agent_token` (`CHAOSDESK_AGENT_TOKEN`), so single-site installs and a published 1.0 config need no change.
- `Agent\AgentClient`, obtained via `ChaosDesk::agent($site)`: `tickets()`, `ticket()`, `update()`, `reply()`, `note()`, `anonymiseAuthor()`, `agents()`, `categories()`, `priorities()` and `sites()` against the ChaosDesk agent API with a team bearer token. `reply()` and `note()` send an `Idempotency-Key` and accept an `agentName` for attribution.
- `Webhooks\Signature::verify()` and `Signature::sign()` for the `X-ChaosDesk-Signature` header (constant-time HMAC-SHA256 over the raw body).
- `Http\Concerns\TalksToChaosDesk`: the HTTP plumbing (base url, timeout, retries, idempotency headers, exception mapping) shared by the ingest and agent clients.
- `ChaosDeskException` factories `notFound()`, `validation()`, `unavailable()` and `missingAgentToken($site)`, a `$site` parameter on `missingToken()`, and the predicates `isUnauthorised()`, `isNotFound()` and `isUnavailable()`. A connection failure surfaces without the request url, so a per-ticket access token never ends up in the message.
- `tags` on ticket creation (at most 10, each `^[a-z0-9-]{1,32}$`, distinct) and limits on `context.extra` (at most 20 keys matching `^[A-Za-z0-9_.-]{1,64}$`, scalar values of at most 255 characters), enforced by `StoreTicketRequest` to mirror the ingest endpoint.
- `chaosdesk.migrations` (`CHAOSDESK_MIGRATIONS`) to stop the package from registering its migrations when the host keeps ticket references in its own store, and the `chaosdesk-migrations` publish tag.
- `resources/lang/en/chaosdesk.php` with every string the Livewire components render, loaded under the `chaosdesk::` namespace and publishable with `--tag=chaosdesk-lang`.
- Migration `0001_01_01_000001_add_site_to_chaosdesk_tickets_table`: a `site` column (default `default`), a nullable `ticket_id`, and a unique index on `(site, ticket_ulid)` replacing the one on `ticket_ulid`.
- Test helpers `fakeChaosDeskAgent()` and the agent payload builders in `tests/Pest.php`.

### Changed

- `Contracts\TicketStore`: `remember()` takes a `string $site = 'default'` parameter, `forUser()` and `find()` take a `?string $site = null` to scope the lookup; the remembered ticket array may carry `id`. Custom implementations add the parameter.
- `Support\TicketReference` gains `site` and `id` after the existing positional arguments; `toArray()` includes both.
- `Storage\DatabaseTicketStore` writes and filters on `site` and stores `ticket_id`.
- `Context\ContextCollector`: a client-reported `app.version` and `app.build` now win over the server's values, so a mobile app reports the binary the user actually runs.
- The Livewire views and components read their strings through `__('chaosdesk::chaosdesk.*')` instead of bare English keys; the rendered English is unchanged.
- `ChaosDesk::VERSION` is `1.1.0`, reported in `context.sdk` and the `User-Agent`.

## [1.0.0] - 2026-09-11

### Added

- `ChaosDesk` ingest client: `config()`, `createTicket()`, `ticket()`, `reply()`, `attach()`, `attachContents()`, `isConfigured()` and `routes()`, authenticating with the site token.
- `Context\ContextCollector` building the diagnostic context (`source`, `sdk`, `app`, `runtime`, `page`, `device`, `user`, `console`, `extra`) with per-group switches in `chaosdesk.context`.
- `Contracts\ProvidesSupportContext` for a stable external id and extra user facts on the host's user model.
- Livewire components `chaosdesk-support` and `chaosdesk-tickets`, styled with plain Tailwind, with the `chaosdesk.js` browser helper for console capture and screenshots.
- `Contracts\TicketStore` with `Storage\DatabaseTicketStore` on the `chaosdesk_tickets` table, and `Support\TicketReference`.
- `Http\Controllers\TicketController` and `Http\Requests\StoreTicketRequest` behind `ChaosDesk::routes()` for mobile clients.
- Publish tags `chaosdesk-config`, `chaosdesk-views` and `chaosdesk-assets`.

[Unreleased]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/releases/tag/v1.0.0
