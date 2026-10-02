<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ChaosDesk community strings
|--------------------------------------------------------------------------
|
| Every string the community components render, under the
| `chaosdesk::community` namespace. Publish with
| `php artisan vendor:publish --tag=chaosdesk-lang` to change them.
| Posts themselves are never translated.
|
*/

return [

    'guest' => 'Sign in to take part in the community.',
    'blocked' => 'You can read along, but you can no longer post or vote on this board.',

    'board' => [
        'new_thread' => 'New thread',
        'filters' => 'Filter threads',
        'kind' => 'Type',
        'status' => 'Status',
        'sort' => 'Sort by',
        'all_kinds' => 'All types',
        'all_statuses' => 'All statuses',
        'empty' => 'No threads here yet.',
        'pinned' => 'Pinned',
        'locked' => 'Locked',
        'replies' => ':count reply|:count replies',
        'by' => 'by :name',
        'pagination' => 'Thread pages',
        'previous' => 'Previous page',
        'next' => 'Next page',
        'page' => 'Page :current of :last',
        'open_polls' => ':count open poll|:count open polls',
        'answered' => 'Answered',
        'view_polls' => 'View polls',
        'back' => 'Back to the board',
    ],

    'kinds' => [
        'discussion' => 'Discussion',
        'proposal' => 'Proposal',
        'bug' => 'Bug report',
    ],

    'statuses' => [
        'open' => 'Open',
        'planned' => 'Planned',
        'in_progress' => 'In progress',
        'done' => 'Done',
        'declined' => 'Declined',
    ],

    'sorts' => [
        'activity' => 'Recent activity',
        'votes' => 'Most votes',
        'newest' => 'Newest',
    ],

    'vote' => [
        'count' => ':count vote|:count votes',
        'add' => 'Upvote: :title',
        'remove' => 'Withdraw your upvote: :title',
    ],

    'thread' => [
        'started_by' => 'Started by :name',
        'official' => 'Official',
        'team' => 'Team',
        'you' => 'You',
        'declined_reason' => 'Why we declined: :reason',
        'locked' => 'This thread is locked. You can read it, but no longer reply.',
        'replies' => 'Replies',
        'no_replies' => 'No replies yet.',
        'reply' => 'Your reply',
        'send_reply' => 'Post reply',
        'sending' => 'Posting…',
    ],

    'new_thread' => [
        'heading' => 'Start a thread',
        'kind' => 'Type',
        'title' => 'Title',
        'body' => 'Message',
        'submit' => 'Post thread',
        'submitting' => 'Posting…',
        'cancel' => 'Cancel',
        'posted' => 'Your thread is posted.',
        'another' => 'Start another thread',
        'no_kinds' => 'This board does not take new threads.',
    ],

    'polls' => [
        'heading' => 'Polls',
        'empty' => 'There are no polls yet.',
        'open' => 'Open',
        'closed' => 'Closed',
        'closes' => 'Closes :date',
        'closed_on' => 'Closed :date',
        'single' => 'Pick one answer.',
        'multiple' => 'Pick one or more answers.',
        'submit' => 'Send answer',
        'change' => 'Change answer',
        'submitting' => 'Saving…',
        'saved' => 'Thanks, your answer is saved.',
        'choose' => 'Choose an answer first.',
        'respondents' => ':count respondent|:count respondents',
        'option_result' => ':count (:percent%)',
        'results_pending' => 'Results are not available yet.',
    ],

    'charter' => [
        'heading' => 'Community charter',
        'intro' => 'Please read the charter and accept it before you take part.',
        'version' => 'Version :version',
        'accept' => 'I accept the charter',
        'accepting' => 'Saving…',
        'accepted' => 'Thanks, you accepted the charter.',
        'already_accepted' => 'You accepted this version of the charter.',
        'changed' => 'The charter changed while you were reading. Please read the new version and accept it again.',
        'empty' => 'This board has no charter text.',
        'continue' => 'Go to the board',
    ],

    'errors' => [
        'blocked' => 'You can no longer post or vote on this board.',
        'charter_required' => 'Please accept the community charter first.',
        'locked' => 'This thread is locked and takes no new replies.',
        'poll_closed' => 'This poll has closed.',
        'poll_not_open' => 'This poll is not open yet.',
        'poll_results_hidden' => 'Results are shown once the poll closes.',
        'not_votable' => 'Only proposals take votes.',
        'not_found' => 'This could not be found. It may have been removed.',
        'identity_conflict' => 'Your account could not be matched to a community member. Please contact support.',
        'unavailable' => 'The community is unavailable right now. Please try again shortly.',
        'generic' => 'Something went wrong. Please try again.',
    ],

];
