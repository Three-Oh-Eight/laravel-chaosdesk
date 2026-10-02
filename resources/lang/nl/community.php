<?php

declare(strict_types=1);

return [

    'guest' => 'Log in om mee te doen met de community.',
    'blocked' => 'Je kunt meelezen, maar je kunt op dit board niet meer posten of stemmen.',

    'board' => [
        'new_thread' => 'Nieuw onderwerp',
        'filters' => 'Onderwerpen filteren',
        'kind' => 'Soort',
        'status' => 'Status',
        'sort' => 'Sorteren op',
        'all_kinds' => 'Alle soorten',
        'all_statuses' => 'Alle statussen',
        'empty' => 'Hier staan nog geen onderwerpen.',
        'pinned' => 'Vastgezet',
        'locked' => 'Gesloten',
        'replies' => ':count reactie|:count reacties',
        'by' => 'door :name',
        'pagination' => 'Pagina\'s met onderwerpen',
        'previous' => 'Vorige pagina',
        'next' => 'Volgende pagina',
        'page' => 'Pagina :current van :last',
        'open_polls' => ':count open peiling|:count open peilingen',
        'answered' => 'Beantwoord',
        'view_polls' => 'Peilingen bekijken',
        'back' => 'Terug naar het board',
    ],

    'kinds' => [
        'discussion' => 'Discussie',
        'proposal' => 'Voorstel',
        'bug' => 'Bugmelding',
    ],

    'statuses' => [
        'open' => 'Open',
        'planned' => 'Gepland',
        'in_progress' => 'In behandeling',
        'done' => 'Klaar',
        'declined' => 'Afgewezen',
    ],

    'sorts' => [
        'activity' => 'Recente activiteit',
        'votes' => 'Meeste stemmen',
        'newest' => 'Nieuwste',
    ],

    'vote' => [
        'count' => ':count stem|:count stemmen',
        'add' => 'Stem voor: :title',
        'remove' => 'Trek je stem in: :title',
    ],

    'thread' => [
        'started_by' => 'Gestart door :name',
        'official' => 'Officieel',
        'team' => 'Team',
        'you' => 'Jij',
        'declined_reason' => 'Waarom we het afwijzen: :reason',
        'locked' => 'Dit onderwerp is gesloten. Je kunt het lezen, maar niet meer reageren.',
        'replies' => 'Reacties',
        'no_replies' => 'Nog geen reacties.',
        'reply' => 'Jouw reactie',
        'send_reply' => 'Reactie plaatsen',
        'sending' => 'Bezig met plaatsen…',
    ],

    'new_thread' => [
        'heading' => 'Start een onderwerp',
        'kind' => 'Soort',
        'title' => 'Titel',
        'body' => 'Bericht',
        'submit' => 'Onderwerp plaatsen',
        'submitting' => 'Bezig met plaatsen…',
        'cancel' => 'Annuleren',
        'posted' => 'Je onderwerp is geplaatst.',
        'another' => 'Nog een onderwerp starten',
        'no_kinds' => 'Op dit board kun je geen nieuwe onderwerpen starten.',
    ],

    'polls' => [
        'heading' => 'Peilingen',
        'empty' => 'Er zijn nog geen peilingen.',
        'open' => 'Open',
        'closed' => 'Gesloten',
        'closes' => 'Sluit :date',
        'closed_on' => 'Gesloten :date',
        'single' => 'Kies een antwoord.',
        'multiple' => 'Kies een of meer antwoorden.',
        'submit' => 'Antwoord versturen',
        'change' => 'Antwoord wijzigen',
        'submitting' => 'Bezig met opslaan…',
        'saved' => 'Bedankt, je antwoord is opgeslagen.',
        'choose' => 'Kies eerst een antwoord.',
        'respondents' => ':count deelnemer|:count deelnemers',
        'option_result' => ':count (:percent%)',
        'results_pending' => 'De uitslag is nog niet beschikbaar.',
    ],

    'charter' => [
        'heading' => 'Communityregels',
        'intro' => 'Lees de regels en ga ermee akkoord voordat je meedoet.',
        'version' => 'Versie :version',
        'accept' => 'Ik ga akkoord met de regels',
        'accepting' => 'Bezig met opslaan…',
        'accepted' => 'Bedankt, je bent akkoord gegaan met de regels.',
        'already_accepted' => 'Je bent akkoord gegaan met deze versie van de regels.',
        'changed' => 'De regels zijn gewijzigd terwijl je las. Lees de nieuwe versie en ga er opnieuw mee akkoord.',
        'empty' => 'Dit board heeft geen regels.',
        'continue' => 'Naar het board',
    ],

    'errors' => [
        'blocked' => 'Je kunt op dit board niet meer posten of stemmen.',
        'charter_required' => 'Ga eerst akkoord met de communityregels.',
        'locked' => 'Dit onderwerp is gesloten en neemt geen nieuwe reacties aan.',
        'poll_closed' => 'Deze peiling is gesloten.',
        'poll_not_open' => 'Deze peiling is nog niet geopend.',
        'poll_results_hidden' => 'De uitslag verschijnt zodra de peiling sluit.',
        'not_votable' => 'Je kunt alleen op voorstellen stemmen.',
        'not_found' => 'Dit is niet gevonden. Mogelijk is het verwijderd.',
        'identity_conflict' => 'Je account kon niet aan een communitylid worden gekoppeld. Neem contact op met support.',
        'unavailable' => 'De community is nu niet bereikbaar. Probeer het zo opnieuw.',
        'generic' => 'Er ging iets mis. Probeer het opnieuw.',
    ],

];
