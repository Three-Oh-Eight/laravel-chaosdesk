<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ChaosDesk SDK strings
|--------------------------------------------------------------------------
|
| Every string the shipped Livewire components render. Publish this file
| with `php artisan vendor:publish --tag=chaosdesk-lang` and add a sibling
| directory per locale (`lang/vendor/chaosdesk/nl/chaosdesk.php`) to
| translate the components without touching the views.
|
*/

return [

    'form' => [
        'thanks_title' => 'Thanks, we have your message',
        'thanks_body' => 'We will reply by email. You can keep using the app in the meantime.',
        'send_another' => 'Send another',
        'name' => 'Your name',
        'email' => 'Email',
        'email_required' => 'An email address is required.',
        'topic' => 'Topic',
        'choose_topic' => 'Choose a topic',
        'subject' => 'Subject',
        'message' => 'What happened?',
        'screenshot_preview' => 'Screenshot preview',
        'screenshot_attached' => 'Screenshot attached',
        'remove' => 'Remove',
        'attach_screenshot' => 'Attach a screenshot',
        'waiting_for_browser' => 'Waiting for the browser…',
        'capture_hint' => 'Your browser will ask which window or tab to share. Nothing is captured until you choose.',
        'send' => 'Send',
        'sending' => 'Sending…',
        'context_notice' => 'We include your page, app version and recent errors to help us diagnose faster.',
    ],

    'tickets' => [
        'back' => 'Back to all tickets',
        'support' => 'Support',
        'reply' => 'Reply',
        'send_reply' => 'Send reply',
        'sending' => 'Sending…',
        'empty' => 'You have not raised any support tickets yet.',
        'not_found' => 'That ticket could not be found.',
    ],

];
