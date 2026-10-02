<?php

declare(strict_types=1);

return [

    'guest' => 'Melde dich an, um bei der Community mitzumachen.',
    'blocked' => 'Du kannst mitlesen, aber in diesem Board nicht mehr posten oder abstimmen.',

    'board' => [
        'new_thread' => 'Neues Thema',
        'filters' => 'Themen filtern',
        'kind' => 'Art',
        'status' => 'Status',
        'sort' => 'Sortieren nach',
        'all_kinds' => 'Alle Arten',
        'all_statuses' => 'Alle Status',
        'empty' => 'Hier gibt es noch keine Themen.',
        'pinned' => 'Angeheftet',
        'locked' => 'Gesperrt',
        'replies' => ':count Antwort|:count Antworten',
        'by' => 'von :name',
        'pagination' => 'Themenseiten',
        'previous' => 'Vorherige Seite',
        'next' => 'Nächste Seite',
        'page' => 'Seite :current von :last',
        'open_polls' => ':count offene Umfrage|:count offene Umfragen',
        'answered' => 'Beantwortet',
        'view_polls' => 'Umfragen ansehen',
        'back' => 'Zurück zum Board',
    ],

    'kinds' => [
        'discussion' => 'Diskussion',
        'proposal' => 'Vorschlag',
        'bug' => 'Fehlermeldung',
    ],

    'statuses' => [
        'open' => 'Offen',
        'planned' => 'Geplant',
        'in_progress' => 'In Arbeit',
        'done' => 'Erledigt',
        'declined' => 'Abgelehnt',
    ],

    'sorts' => [
        'activity' => 'Letzte Aktivität',
        'votes' => 'Meiste Stimmen',
        'newest' => 'Neueste',
    ],

    'vote' => [
        'count' => ':count Stimme|:count Stimmen',
        'add' => 'Dafür stimmen: :title',
        'remove' => 'Stimme zurückziehen: :title',
    ],

    'thread' => [
        'started_by' => 'Gestartet von :name',
        'official' => 'Offiziell',
        'team' => 'Team',
        'you' => 'Du',
        'declined_reason' => 'Warum wir ablehnen: :reason',
        'locked' => 'Dieses Thema ist gesperrt. Du kannst es lesen, aber nicht mehr antworten.',
        'replies' => 'Antworten',
        'no_replies' => 'Noch keine Antworten.',
        'reply' => 'Deine Antwort',
        'send_reply' => 'Antwort posten',
        'sending' => 'Wird gepostet…',
    ],

    'new_thread' => [
        'heading' => 'Thema starten',
        'kind' => 'Art',
        'title' => 'Titel',
        'body' => 'Nachricht',
        'submit' => 'Thema posten',
        'submitting' => 'Wird gepostet…',
        'cancel' => 'Abbrechen',
        'posted' => 'Dein Thema ist gepostet.',
        'another' => 'Weiteres Thema starten',
        'no_kinds' => 'In diesem Board können keine neuen Themen gestartet werden.',
    ],

    'polls' => [
        'heading' => 'Umfragen',
        'empty' => 'Es gibt noch keine Umfragen.',
        'open' => 'Offen',
        'closed' => 'Beendet',
        'closes' => 'Endet :date',
        'closed_on' => 'Beendet :date',
        'single' => 'Wähle eine Antwort.',
        'multiple' => 'Wähle eine oder mehrere Antworten.',
        'submit' => 'Antwort senden',
        'change' => 'Antwort ändern',
        'submitting' => 'Wird gespeichert…',
        'saved' => 'Danke, deine Antwort ist gespeichert.',
        'choose' => 'Wähle zuerst eine Antwort.',
        'respondents' => ':count Teilnehmer|:count Teilnehmende',
        'option_result' => ':count (:percent %)',
        'results_pending' => 'Die Ergebnisse sind noch nicht verfügbar.',
    ],

    'charter' => [
        'heading' => 'Community-Regeln',
        'intro' => 'Bitte lies die Regeln und akzeptiere sie, bevor du mitmachst.',
        'version' => 'Version :version',
        'accept' => 'Ich akzeptiere die Regeln',
        'accepting' => 'Wird gespeichert…',
        'accepted' => 'Danke, du hast die Regeln akzeptiert.',
        'already_accepted' => 'Du hast diese Version der Regeln akzeptiert.',
        'changed' => 'Die Regeln haben sich geändert, während du gelesen hast. Bitte lies die neue Version und akzeptiere sie erneut.',
        'empty' => 'Dieses Board hat keine Regeln.',
        'continue' => 'Zum Board',
    ],

    'errors' => [
        'blocked' => 'Du kannst in diesem Board nicht mehr posten oder abstimmen.',
        'charter_required' => 'Bitte akzeptiere zuerst die Community-Regeln.',
        'locked' => 'Dieses Thema ist gesperrt und nimmt keine neuen Antworten an.',
        'poll_closed' => 'Diese Umfrage ist beendet.',
        'poll_not_open' => 'Diese Umfrage ist noch nicht geöffnet.',
        'poll_results_hidden' => 'Die Ergebnisse erscheinen, sobald die Umfrage endet.',
        'not_votable' => 'Nur für Vorschläge kann abgestimmt werden.',
        'not_found' => 'Nicht gefunden. Möglicherweise wurde es entfernt.',
        'identity_conflict' => 'Dein Konto konnte keinem Community-Mitglied zugeordnet werden. Bitte wende dich an den Support.',
        'unavailable' => 'Die Community ist gerade nicht erreichbar. Bitte versuche es gleich noch einmal.',
        'generic' => 'Etwas ist schiefgelaufen. Bitte versuche es erneut.',
    ],

];
