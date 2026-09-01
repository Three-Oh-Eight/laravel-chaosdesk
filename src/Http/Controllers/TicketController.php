<?php

declare(strict_types=1);

namespace ThreeOhEight\ChaosDesk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use ThreeOhEight\ChaosDesk\ChaosDesk;
use ThreeOhEight\ChaosDesk\Contracts\TicketStore;
use ThreeOhEight\ChaosDesk\Exceptions\ChaosDeskException;
use ThreeOhEight\ChaosDesk\Http\Requests\StoreTicketRequest;
use ThreeOhEight\ChaosDesk\Support\Identity;
use ThreeOhEight\ChaosDesk\Support\TicketReference;

/**
 * The endpoints your iOS and Android apps talk to.
 *
 * They authenticate against your application, exactly as they already do for
 * everything else; this controller forwards to ChaosDesk over the site token
 * and keeps a local reference so the user can see their own tickets later.
 */
class TicketController extends Controller
{
    public function __construct(
        protected ChaosDesk $chaosDesk,
        protected TicketStore $store,
    ) {}

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $user = Auth::user();
        $externalId = Identity::externalId($user);

        $email = $request->validated('email') ?? Identity::email($user);

        if ($email === null) {
            return response()->json([
                'message' => 'An email address is required to raise a ticket.',
            ], 422);
        }

        try {
            $result = $this->chaosDesk->createTicket(
                attributes: [
                    'email' => $email,
                    'name' => $request->validated('name') ?? Identity::name($user),
                    'subject' => $request->validated('subject'),
                    'message' => $request->validated('message'),
                    'category_id' => $request->validated('category_id'),
                    'priority_id' => $request->validated('priority_id'),
                    'custom_fields' => $request->validated('custom_fields'),
                ],
                clientContext: $request->validated('context') ?? [],
                user: $user,
            );
        } catch (ChaosDeskException $e) {
            return response()->json(
                array_filter(['message' => $e->getMessage(), 'errors' => $e->errors]),
                $e->isValidationError() ? 422 : 502,
            );
        }

        if ($externalId !== null) {
            $this->store->remember($externalId, [
                'ulid' => $result['ticket']['ulid'],
                'access_token' => $result['access_token'],
                'subject' => $result['ticket']['subject'],
            ]);
        }

        return response()->json($result, 201);
    }

    public function index(): JsonResponse
    {
        $externalId = Identity::externalId(Auth::user());

        if ($externalId === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->store->forUser($externalId)
                ->map(static fn (TicketReference $ticket): array => $ticket->toArray())
                ->all(),
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $reference = $this->resolve($ulid);

        if ($reference === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json($this->chaosDesk->ticket($ulid, $reference->accessToken));
    }

    public function reply(Request $request, string $ulid): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $reference = $this->resolve($ulid);

        if ($reference === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json(
            $this->chaosDesk->reply($ulid, $reference->accessToken, $validated['body']),
            201,
        );
    }

    public function attach(Request $request, string $ulid): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', (array) config('chaosdesk.attachments.accepted')),
                'max:'.config('chaosdesk.attachments.max_kilobytes'),
            ],
        ]);

        $reference = $this->resolve($ulid);

        if ($reference === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json(
            $this->chaosDesk->attach($ulid, $reference->accessToken, $validated['file']),
            201,
        );
    }

    /**
     * Look up a ticket reference, but only one belonging to the current user.
     */
    protected function resolve(string $ulid): ?TicketReference
    {
        $externalId = Identity::externalId(Auth::user());

        return $externalId === null ? null : $this->store->find($externalId, $ulid);
    }
}
