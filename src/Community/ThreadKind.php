<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community;

/**
 * The kinds of thread a board can allow. Only proposals take votes.
 */
enum ThreadKind: string
{
    case Discussion = 'discussion';
    case Proposal = 'proposal';
    case Bug = 'bug';
}
