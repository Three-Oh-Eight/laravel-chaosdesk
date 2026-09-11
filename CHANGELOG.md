# Changelog

All notable changes to this package are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Three-Oh-Eight/laravel-chaosdesk/releases/tag/v1.0.0
