<?php

declare(strict_types=1);

use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Exceptions\CommunityException;
use ThreeOhEight\ChaosDesk\Support\CommunityText;

it('labels kinds, statuses and sorts from the translations', function (): void {
    expect(CommunityText::kind('proposal'))->toBe('Proposal')
        ->and(CommunityText::kind('bug'))->toBe('Bug report')
        ->and(CommunityText::status('in_progress'))->toBe('In progress')
        ->and(CommunityText::status('declined'))->toBe('Declined')
        ->and(CommunityText::sort('votes'))->toBe('Most votes');
});

it('follows the application locale', function (): void {
    $nl = require __DIR__.'/../resources/lang/nl/community.php';

    app()->setLocale('nl');

    expect(CommunityText::kind('proposal'))->toBe($nl['kinds']['proposal'])
        ->and(CommunityText::status('done'))->toBe($nl['statuses']['done']);
});

it('falls back to a headline for a value it has no label for', function (): void {
    expect(CommunityText::kind('feature_request'))->toBe('Feature Request')
        ->and(CommunityText::status('on_hold'))->toBe('On Hold')
        ->and(CommunityText::sort('loudest'))->toBe('Loudest');
});

it('turns refusal codes into member-facing messages', function (string $code, int $status, string $message): void {
    expect(CommunityText::error(new CommunityException($code, 'Raw API refusal text.', $status)))->toBe($message);
})->with([
    'blocked' => [CommunityException::MEMBER_BLOCKED, 403, 'You can no longer post or vote on this board.'],
    'charter not accepted' => [CommunityException::CHARTER_NOT_ACCEPTED, 403, 'Please accept the community charter first.'],
    'charter version mismatch' => [CommunityException::CHARTER_VERSION_MISMATCH, 409, 'Please accept the community charter first.'],
    'thread locked' => [CommunityException::THREAD_LOCKED, 403, 'This thread is locked and takes no new replies.'],
    'poll closed' => [CommunityException::POLL_CLOSED, 403, 'This poll has closed.'],
    'poll not open' => [CommunityException::POLL_NOT_OPEN, 403, 'This poll is not open yet.'],
    'poll results hidden' => [CommunityException::POLL_RESULTS_HIDDEN, 403, 'Results are shown once the poll closes.'],
    'not votable' => [CommunityException::THREAD_NOT_VOTABLE, 422, 'Only proposals take votes.'],
    'identity conflict' => [CommunityException::MEMBER_IDENTITY_CONFLICT, 409, 'Your account could not be matched to a community member. Please contact support.'],
    'thread not found' => [CommunityException::THREAD_NOT_FOUND, 404, 'This could not be found. It may have been removed.'],
    'unknown code' => ['brand_new_code', 400, 'Something went wrong. Please try again.'],
]);

it('maps plain failures without a code', function (): void {
    expect(CommunityText::error(ChaosDeskException::notFound()))->toBe('This could not be found. It may have been removed.')
        ->and(CommunityText::error(ChaosDeskException::unavailable()))->toBe('The community is unavailable right now. Please try again shortly.')
        ->and(CommunityText::error(ChaosDeskException::unauthorised()))->toBe('Something went wrong. Please try again.');
});

it('never returns the raw api message', function (): void {
    expect(CommunityText::error(new CommunityException('brand_new_code', 'Raw API refusal text.', 403)))
        ->not->toContain('Raw API refusal text.');
});

it('renders markdown with raw html escaped and unsafe links dropped', function (): void {
    $html = CommunityText::markdown("# Rules\n\nBe **kind**.\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[a](javascript:alert(1)) [b](data:text/html,x) [c](https://example.test)");

    expect($html)->toContain('<h1>Rules</h1>')
        ->toContain('<strong>kind</strong>')
        ->toContain('&lt;script&gt;')
        ->toContain('&lt;img')
        ->toContain('href="https://example.test"')
        ->not->toContain('<script')
        ->not->toContain('<img')
        ->not->toContain('javascript:')
        ->not->toContain('data:text/html');
});

it('renders empty markdown as an empty string', function (?string $markdown): void {
    expect(CommunityText::markdown($markdown))->toBe('');
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ["  \n "],
]);
