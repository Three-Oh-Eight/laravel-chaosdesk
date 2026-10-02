{{-- A thread status badge. Expects $status, the raw API value. --}}
<span @class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => ! in_array($status, ['planned', 'in_progress', 'done', 'declined'], true),
    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200' => $status === 'planned',
    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => $status === 'in_progress',
    'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' => $status === 'done',
    'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' => $status === 'declined',
])>{{ \ThreeOhEight\ChaosDesk\Support\CommunityText::status($status) }}</span>
