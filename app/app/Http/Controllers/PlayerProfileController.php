<?php

namespace App\Http\Controllers;

use App\Models\GameEvent;
use App\Services\PlayerStatsService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlayerProfileController extends Controller
{
    public function __construct(
        private readonly PlayerStatsService $playerStatsService,
    ) {}

    public function __invoke(string $username): Response
    {
        $profile = $this->playerStatsService->getPlayerProfile($username);

        if (! $profile) {
            throw new NotFoundHttpException('Player not found');
        }

        return Inertia::render('player-profile', [
            'player' => $profile,
            'recent_events' => Inertia::defer(fn () => GameEvent::query()
                ->where('player', $username)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (GameEvent $event) => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'player' => $event->player,
                    'target' => $event->target,
                    'details' => $event->details,
                    'game_time' => $event->game_time?->toIso8601String(),
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->all()),
        ]);
    }
}
