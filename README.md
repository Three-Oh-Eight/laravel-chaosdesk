# Laravel ChaosDesk SDK

Laravel SDK for [ChaosDesk](https://chaosdesk.eu): a drop-in Livewire support form, automatic diagnostic context, a server-to-server ticket ingest client, and an agent client for building your own inbox.

[![Latest Version](https://img.shields.io/packagist/v/three_oh_eight/laravel-chaosdesk.svg)](https://packagist.org/packages/three_oh_eight/laravel-chaosdesk)
[![License](https://img.shields.io/packagist/l/three_oh_eight/laravel-chaosdesk.svg)](LICENSE)

## Requirements

- PHP 8.4 or 8.5
- Laravel 12 or 13
- Livewire 3 or 4

## Install

```bash
composer require three_oh_eight/laravel-chaosdesk
php artisan migrate
```

```env
CHAOSDESK_URL=https://chaosdesk.eu/api/v1
CHAOSDESK_SITE_TOKEN=your-ingest-token
```

Issue the token in ChaosDesk under **Site settings → Integration**. It is shown once and stored only as a hash.

> **The ingest token is a server-side secret.** It must never reach a browser bundle or a mobile binary. Clients authenticate against *your* application, which forwards to ChaosDesk.

```
iOS app      --(your auth)-->  ┐
Android app  --(your auth)-->  ├--> your backend  --(X-Site-Token)-->  ChaosDesk
your website (Livewire)    --> ┘
```

## Livewire components

Both are styled with plain Tailwind and carry no Flux dependency, so they render in any application.

```blade
{{-- Raise a ticket --}}
<livewire:chaosdesk-support />

{{-- List and reply to the signed-in user's tickets --}}
<livewire:chaosdesk-tickets />
```

Restyle them by publishing the views, translate them by publishing the strings:

```bash
php artisan vendor:publish --tag=chaosdesk-views
php artisan vendor:publish --tag=chaosdesk-lang
```

Every string the components render lives under the `chaosdesk::chaosdesk` namespace (`lang/vendor/chaosdesk/en/chaosdesk.php` once published). Add a directory per locale next to it to translate the form without touching the views.

### Browser context and screenshots

Import the helper once in your bundle. It keeps a rolling buffer of console errors and powers the screenshot button.

```js
// resources/js/app.js
import 'chaosdesk';
```

Screenshots use the browser Screen Capture API. Capture is always explicit: the user presses the button and the browser asks which surface to share. Nothing is captured until they choose.

## Identifying the user

Implement the contract on your `User` model and every ticket carries a stable identity. ChaosDesk keys the author on the external id, so the same person stays one author even after an email change.

```php
use ThreeOhEight\ChaosDesk\Contracts\ProvidesSupportContext;

class User extends Authenticatable implements ProvidesSupportContext
{
    public function supportExternalId(): string
    {
        return (string) $this->id;
    }

    public function supportContext(): array
    {
        return ['plan' => $this->subscription?->plan_name];
    }
}
```

Without the contract the SDK falls back to the auth identifier.

## Mobile applications

Your apps post to your own backend. Register the receiving endpoints behind whatever authentication you already use:

```php
// routes/api.php
use ThreeOhEight\ChaosDesk\Facades\ChaosDesk;

Route::middleware('auth:sanctum')->group(function () {
    ChaosDesk::routes();
});
```

That registers, under the configurable `chaosdesk` prefix:

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/chaosdesk/tickets` | Raise a ticket |
| `GET` | `/chaosdesk/tickets` | The caller's tickets |
| `GET` | `/chaosdesk/tickets/{ulid}` | One thread |
| `POST` | `/chaosdesk/tickets/{ulid}/messages` | Reply |
| `POST` | `/chaosdesk/tickets/{ulid}/attachments` | Attach a file |

A ticket can only be read or replied to by the user who raised it.

## Programmatic use

```php
use ThreeOhEight\ChaosDesk\Facades\ChaosDesk;

$result = ChaosDesk::createTicket([
    'email' => 'customer@example.com',
    'subject' => 'Cannot save my settings',
    'message' => 'It reverts every time.',
]);

$ulid = $result['ticket']['ulid'];
$accessToken = $result['access_token'];

ChaosDesk::ticket($ulid, $accessToken);
ChaosDesk::reply($ulid, $accessToken, 'Any news?');
```

Failures throw `ChaosDeskException`, which carries the HTTP status and any validation errors. Ask it what went wrong instead of matching on the status:

| Predicate | True when |
| --- | --- |
| `isValidationError()` | ChaosDesk answered 422; `$e->errors` holds the messages keyed by field |
| `isUnauthorised()` | the credentials were rejected (401) or may not reach the resource (403) |
| `isNotFound()` | 404 |
| `isUnavailable()` | ChaosDesk answered 5xx or could not be reached at all |

## Diagnostic context

Collected automatically and shown to agents beside the ticket:

| Group | Source | Contents |
| --- | --- | --- |
| `app` | server | name, version, environment |
| `runtime` | server | PHP and framework versions |
| `page` | browser | url, referrer, viewport, user agent, locale |
| `device` | client | platform, OS version, model, locale, timezone |
| `user` | server | external id, name, email, plan |
| `console` | browser | up to 50 recent errors |
| `extra` | you | anything |

Turn any group off in `config/chaosdesk.php`. Set the reported version with `APP_VERSION` or `chaosdesk.context.app_version`; a client that reports its own `app.version` and `app.build` (a mobile binary, for instance) wins over the server's value.

### Tags and extra context

A ticket may carry up to 10 tags, each matching `^[a-z0-9-]{1,32}$` and distinct, so your inbox can filter on them (`in-session`, `complaint`, ...). Pass them as `tags` next to the subject and message.

`context.extra` is a flat map for anything else you want an agent to see: at most 20 keys, each matching `^[A-Za-z0-9_.-]{1,64}$`, each value a scalar of at most 255 characters. The bundled `StoreTicketRequest` enforces the same limits as the ChaosDesk ingest endpoint, so a bad payload fails in your application with a readable 422 rather than as a bad gateway.

```php
ChaosDesk::createTicket([
    'subject' => 'Call dropped halfway',
    'message' => 'The audio cut out after ten minutes.',
    'tags' => ['in-session', 'complaint'],
], clientContext: [
    'extra' => ['chat_id' => 12, 'channel' => 'call', 'duration_seconds' => 612],
], user: $request->user());
```

## Storing ticket references

ChaosDesk returns a per-ticket access token, which is the credential for reading the thread. The SDK keeps it in a local `chaosdesk_tickets` table so `<livewire:chaosdesk-tickets />` works out of the box. Bind your own `TicketStore` implementation to keep it elsewhere:

```php
$this->app->bind(TicketStore::class, YourTicketStore::class);
```

References are kept per site (see below): `remember()` takes the site name, `forUser()` and `find()` accept one to scope the lookup, and the `chaosdesk_tickets` table is unique on `(site, ticket_ulid)` because a ulid is only unique within one ChaosDesk site. `TicketReference` exposes the `site` and the numeric ticket `id` next to the ulid.

## Several sites

One application can front several ChaosDesk sites, for example one per audience. Name them under `chaosdesk.sites`; each carries its own ingest token, an optional agent token (a team API token, for the agent client below) and the numeric id ChaosDesk assigned to the site:

```php
// config/chaosdesk.php
'sites' => [
    'customers' => [
        'token' => env('CHAOSDESK_SITE_TOKEN_CUSTOMERS'),
        'agent_token' => env('CHAOSDESK_AGENT_TOKEN_CUSTOMERS'),
        'site_id' => 2,
    ],
    'gurus' => [
        'token' => env('CHAOSDESK_SITE_TOKEN_GURUS'),
        'agent_token' => env('CHAOSDESK_AGENT_TOKEN_GURUS'),
        'site_id' => 3,
    ],
],
```

The facade talks to the `default` site. Ask for another one with `forSite()`; the returned client shares the context collector and is otherwise identical:

```php
$result = ChaosDesk::forSite('gurus')->createTicket([...], user: $user);

ChaosDesk::forSite('gurus')->site(); // "gurus"
```

A single-site install needs no change: `default` falls back to `CHAOSDESK_SITE_TOKEN` when `chaosdesk.sites.default.token` is empty. A missing token throws `ChaosDeskException` naming the site before any request is sent.

Hosts that keep ticket references in their own store can stop the package from registering the `chaosdesk_tickets` migrations:

```env
CHAOSDESK_MIGRATIONS=false
```

Publishing them stays possible either way (`php artisan vendor:publish --tag=chaosdesk-migrations`).

## Agent client

The agent client acts on a site with a team API token: it lists and updates tickets, replies as an agent, leaves internal notes and anonymises authors. It is what you build an inbox from. Like the ingest token, the agent token is a server-side secret: issue a team API token in ChaosDesk, scope it to the site, and keep it in `chaosdesk.sites.{name}.agent_token`. A single-site install can set `CHAOSDESK_AGENT_TOKEN` instead: the `default` site falls back to `chaosdesk.agent_token`, so a config published from 1.0 works without an edit.

```php
$agent = ChaosDesk::agent('customers');   // or ChaosDesk::agent() for the default site
```

| Method | Does |
| --- | --- |
| `tickets($filters, $page, $perPage)` | Lists tickets; returns the paginator (`data`, `links`, `meta`) untouched |
| `ticket($ulid)` | One ticket with its messages (internal notes included) and attachments |
| `update($ulid, $attributes)` | Changes `status`, `category_id`, `priority_id`, `assigned_to` or `tags` |
| `reply($ulid, $body, $internal, $agentName)` | Replies to the requester, or internally when `$internal` is true |
| `note($ulid, $body, $agentName)` | Leaves an internal note the requester never sees |
| `anonymiseAuthor($externalId, $siteId)` | Blanks an author's name and email, for example after an account deletion |
| `agents($siteId)` | The people a ticket on that site can be assigned to |
| `categories($siteId)` | The site's categories |
| `priorities($siteId)` | The site's priorities |
| `sites()` | The sites the token may reach |

```php
$page = $agent->tickets(['status' => ['open', 'pending'], 'tag' => 'complaint'], page: 2);
$ticket = $agent->ticket('01JABCDEFGHIJKLMNOPQRSTUVW');
$agent->update($ticket['ulid'], ['status' => 'pending', 'assigned_to' => 3, 'tags' => ['complaint']]);
$agent->reply($ticket['ulid'], 'We are looking into it.', agentName: $request->user()->name);
$agent->note($ticket['ulid'], 'Checked the billing log.', agentName: $request->user()->name);
$agent->anonymiseAuthor('user:7', siteId: 2);
$agents = $agent->agents(2);
$categories = $agent->categories(2);
$priorities = $agent->priorities(2);
$sites = $agent->sites();
```

Filters pass through as query parameters; list values encode as `status[]=open&status[]=pending`. Supported filters: `site_id`, `status[]`, `assigned_to`, `unassigned`, `external_id`, `email`, `tag`, `q`, `created_from`, `created_to`, `sort`, `direction`, `include_test`.

`reply()` and `note()` send a fresh `Idempotency-Key` per call, so a retried request replays the first response instead of posting the message twice. Pass `agentName` to have the reply attributed to the person acting through your token: one token typically fronts several of your admins, and ChaosDesk shows the name in its inbox, in the requester mail and in the webhook payload.

`anonymiseAuthor()` is idempotent: repeating the call answers 200 again. The numeric `siteId` is what `sites()` returns; keep it in `chaosdesk.sites.{name}.site_id` so you do not fetch it on every call.

Failures throw the same `ChaosDeskException` as the ingest client, so `isUnauthorised()`, `isNotFound()`, `isValidationError()` and `isUnavailable()` apply. Asking for `ChaosDesk::agent()` on a site without an agent token throws before any request is sent.

## Webhooks

ChaosDesk can call your application on `ticket.created`, `ticket.replied`, `ticket.status_changed`, `ticket.assigned` and the SLA events (`ticket.sla_*`). Every delivery signs the raw request body with HMAC-SHA256 under the channel secret and sends the digest as `X-ChaosDesk-Signature: sha256=<hex>`, with the event name in `X-ChaosDesk-Event` and a unique id in `X-ChaosDesk-Delivery`.

Verify against the raw body, never the decoded and re-encoded payload, or the digest will not match:

```php
use ThreeOhEight\ChaosDesk\Webhooks\Signature;

class ChaosDeskWebhookController
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('services.chaosdesk.webhook_secret');

        abort_unless(
            Signature::verify($request->getContent(), $request->header('X-ChaosDesk-Signature'), $secret),
            401,
        );

        $delivery = (string) $request->header('X-ChaosDesk-Delivery');

        if (Cache::add("chaosdesk-delivery:{$delivery}", true, now()->addDay()) === false) {
            return response()->noContent(); // already handled
        }

        match ($request->header('X-ChaosDesk-Event')) {
            'ticket.replied' => ...,
            'ticket.status_changed' => ...,
            default => null,
        };

        return response()->noContent();
    }
}
```

`Signature::verify()` compares in constant time; a missing or malformed header, or an empty secret, never verifies. Exempt the route from CSRF and keep `X-ChaosDesk-Delivery` around for a while: ChaosDesk retries failed deliveries, so the same delivery id can arrive more than once.

`Signature::sign($rawBody, $secret)` produces the header value ChaosDesk would send, which is what you want in a test.

## Upgrading to 1.1

- Run `php artisan migrate`: 1.1 adds a `site` column and a nullable `ticket_id` to `chaosdesk_tickets` and replaces the unique index on `ticket_ulid` with one on `(site, ticket_ulid)`. Existing rows land on `default`. If you publish migrations instead of loading them from the package, publish again with `--tag=chaosdesk-migrations`.
- Implementors of `Contracts\TicketStore` add the `site` parameter: `remember(string $externalId, array $ticket, string $site = 'default')`, `forUser(string $externalId, ?string $site = null)`, `find(string $externalId, string $ulid, ?string $site = null)`. The remembered ticket array may now carry `id`.
- `TicketReference` gains `site` and `id` after the existing positional arguments; code constructing it positionally keeps working.
- A single-site install keeps working without a config change: `chaosdesk.sites.default` falls back to `CHAOSDESK_SITE_TOKEN`.
- The Livewire views now read their strings from the `chaosdesk::chaosdesk` namespace. Published copies of the views keep rendering; publish the strings with `--tag=chaosdesk-lang` to translate them.

## Testing

```bash
composer test
composer lint
composer test:types
```

## License

MIT. See [LICENSE](LICENSE).
