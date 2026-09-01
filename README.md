# Laravel ChaosDesk SDK

Laravel SDK for [ChaosDesk](https://chaosdesk.eu): a drop-in Livewire support form, automatic diagnostic context, and a server-to-server ticket ingest client.

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

Restyle them by publishing the views:

```bash
php artisan vendor:publish --tag=chaosdesk-views
```

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

Failures throw `ChaosDeskException`, which carries the HTTP status and any validation errors.

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

Turn any group off in `config/chaosdesk.php`. Set the reported version with `CHAOSDESK_APP_VERSION` or `chaosdesk.context.app_version`.

## Storing ticket references

ChaosDesk returns a per-ticket access token, which is the credential for reading the thread. The SDK keeps it in a local `chaosdesk_tickets` table so `<livewire:chaosdesk-tickets />` works out of the box. Bind your own `TicketStore` implementation to keep it elsewhere:

```php
$this->app->bind(TicketStore::class, YourTicketStore::class);
```

## Testing

```bash
composer test
composer lint
composer test:types
```

## License

MIT. See [LICENSE](LICENSE).
