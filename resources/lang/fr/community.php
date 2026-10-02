<?php

declare(strict_types=1);

return [

    'guest' => 'Connectez-vous pour participer à la communauté.',
    'blocked' => 'Vous pouvez lire, mais vous ne pouvez plus publier ni voter sur ce forum.',

    'board' => [
        'new_thread' => 'Nouveau sujet',
        'filters' => 'Filtrer les sujets',
        'kind' => 'Type',
        'status' => 'Statut',
        'sort' => 'Trier par',
        'all_kinds' => 'Tous les types',
        'all_statuses' => 'Tous les statuts',
        'empty' => 'Aucun sujet pour le moment.',
        'pinned' => 'Épinglé',
        'locked' => 'Verrouillé',
        'replies' => ':count réponse|:count réponses',
        'by' => 'par :name',
        'pagination' => 'Pages des sujets',
        'previous' => 'Page précédente',
        'next' => 'Page suivante',
        'page' => 'Page :current sur :last',
        'open_polls' => ':count sondage ouvert|:count sondages ouverts',
        'answered' => 'Répondu',
        'view_polls' => 'Voir les sondages',
        'back' => 'Retour au forum',
    ],

    'kinds' => [
        'discussion' => 'Discussion',
        'proposal' => 'Proposition',
        'bug' => 'Signalement de bug',
    ],

    'statuses' => [
        'open' => 'Ouvert',
        'planned' => 'Prévu',
        'in_progress' => 'En cours',
        'done' => 'Terminé',
        'declined' => 'Refusé',
    ],

    'sorts' => [
        'activity' => 'Activité récente',
        'votes' => 'Plus de votes',
        'newest' => 'Plus récents',
    ],

    'vote' => [
        'count' => ':count vote|:count votes',
        'add' => 'Voter pour : :title',
        'remove' => 'Retirer votre vote : :title',
    ],

    'thread' => [
        'started_by' => 'Lancé par :name',
        'official' => 'Officiel',
        'team' => 'Équipe',
        'you' => 'Vous',
        'declined_reason' => 'Pourquoi nous avons refusé : :reason',
        'locked' => 'Ce sujet est verrouillé. Vous pouvez le lire, mais plus y répondre.',
        'replies' => 'Réponses',
        'no_replies' => 'Pas encore de réponses.',
        'reply' => 'Votre réponse',
        'send_reply' => 'Publier la réponse',
        'sending' => 'Publication…',
    ],

    'new_thread' => [
        'heading' => 'Lancer un sujet',
        'kind' => 'Type',
        'title' => 'Titre',
        'body' => 'Message',
        'submit' => 'Publier le sujet',
        'submitting' => 'Publication…',
        'cancel' => 'Annuler',
        'posted' => 'Votre sujet est publié.',
        'another' => 'Lancer un autre sujet',
        'no_kinds' => 'Ce forum n\'accepte pas de nouveaux sujets.',
    ],

    'polls' => [
        'heading' => 'Sondages',
        'empty' => 'Il n\'y a pas encore de sondage.',
        'open' => 'Ouvert',
        'closed' => 'Clos',
        'closes' => 'Se termine :date',
        'closed_on' => 'Clos :date',
        'single' => 'Choisissez une réponse.',
        'multiple' => 'Choisissez une ou plusieurs réponses.',
        'submit' => 'Envoyer la réponse',
        'change' => 'Modifier la réponse',
        'submitting' => 'Enregistrement…',
        'saved' => 'Merci, votre réponse est enregistrée.',
        'choose' => 'Choisissez d\'abord une réponse.',
        'respondents' => ':count participant|:count participants',
        'option_result' => ':count (:percent %)',
        'results_pending' => 'Les résultats ne sont pas encore disponibles.',
    ],

    'charter' => [
        'heading' => 'Charte de la communauté',
        'intro' => 'Veuillez lire la charte et l\'accepter avant de participer.',
        'version' => 'Version :version',
        'accept' => 'J\'accepte la charte',
        'accepting' => 'Enregistrement…',
        'accepted' => 'Merci, vous avez accepté la charte.',
        'already_accepted' => 'Vous avez accepté cette version de la charte.',
        'changed' => 'La charte a changé pendant votre lecture. Veuillez lire la nouvelle version et l\'accepter à nouveau.',
        'empty' => 'Ce forum n\'a pas de charte.',
        'continue' => 'Aller au forum',
    ],

    'errors' => [
        'blocked' => 'Vous ne pouvez plus publier ni voter sur ce forum.',
        'charter_required' => 'Veuillez d\'abord accepter la charte de la communauté.',
        'locked' => 'Ce sujet est verrouillé et n\'accepte plus de réponses.',
        'poll_closed' => 'Ce sondage est clos.',
        'poll_not_open' => 'Ce sondage n\'est pas encore ouvert.',
        'poll_results_hidden' => 'Les résultats seront affichés à la clôture du sondage.',
        'not_votable' => 'Seules les propositions peuvent recevoir des votes.',
        'not_found' => 'Introuvable. Le contenu a peut-être été supprimé.',
        'identity_conflict' => 'Votre compte n\'a pas pu être associé à un membre de la communauté. Veuillez contacter le support.',
        'unavailable' => 'La communauté est indisponible pour le moment. Veuillez réessayer dans un instant.',
        'generic' => 'Une erreur s\'est produite. Veuillez réessayer.',
    ],

];
