<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Community;

/**
 * Where the team stands on a thread. Agents move it; a decline carries a reason.
 */
enum ThreadStatus: string
{
    case Open = 'open';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Declined = 'declined';
}
