<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Exceptions\CommunityException;

/**
 * The translated text the community components render: labels for kinds and
 * statuses, friendly messages for refusals, and the charter as safe HTML.
 */
final class CommunityText
{
    public static function kind(string $kind): string
    {
        return self::label('kinds', $kind);
    }

    public static function status(string $status): string
    {
        return self::label('statuses', $status);
    }

    public static function sort(string $sort): string
    {
        return self::label('sorts', $sort);
    }

    /**
     * A message a member can act on, never the raw API text.
     */
    public static function error(ChaosDeskException $e): string
    {
        $key = match (true) {
            $e instanceof CommunityException && $e->isMemberBlocked() => 'blocked',
            $e instanceof CommunityException && $e->requiresCharterAcceptance() => 'charter_required',
            $e instanceof CommunityException && $e->isThreadLocked() => 'locked',
            $e instanceof CommunityException && $e->is(CommunityException::POLL_CLOSED) => 'poll_closed',
            $e instanceof CommunityException && $e->is(CommunityException::POLL_NOT_OPEN) => 'poll_not_open',
            $e instanceof CommunityException && $e->is(CommunityException::POLL_RESULTS_HIDDEN) => 'poll_results_hidden',
            $e instanceof CommunityException && $e->is(CommunityException::THREAD_NOT_VOTABLE) => 'not_votable',
            $e instanceof CommunityException && $e->is(CommunityException::MEMBER_IDENTITY_CONFLICT) => 'identity_conflict',
            $e->isNotFound() => 'not_found',
            $e->isUnavailable() => 'unavailable',
            default => 'generic',
        };

        return (string) __("chaosdesk::community.errors.{$key}");
    }

    /**
     * Render member-facing markdown with every raw HTML tag escaped and
     * javascript:, vbscript: and data: links dropped.
     */
    public static function markdown(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        if (! class_exists(CommonMarkConverter::class)) {
            return nl2br(e($markdown));
        }

        return (string) Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    private static function label(string $group, string $value): string
    {
        $key = "chaosdesk::community.{$group}.{$value}";

        return Lang::has($key) ? (string) __($key) : Str::headline($value);
    }
}
