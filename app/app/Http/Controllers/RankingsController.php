<?php

namespace App\Http\Controllers;

use App\Services\PlayerStatsService;
use Inertia\Inertia;
use Inertia\Response;

class RankingsController extends Controller
{
    public function __construct(
        private readonly PlayerStatsService $playerStatsService,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('rankings', [
            'server_stats' => $this->playerStatsService->getServerStats(),
            'leaderboard_kills' => Inertia::defer(fn () => $this->playerStatsService->getFullLeaderboard('zombie_kills', 25)),
            'leaderboard_survival' => Inertia::defer(fn () => $this->playerStatsService->getFullLeaderboard('hours_survived', 25)),
            'leaderboard_deaths' => Inertia::defer(fn () => $this->playerStatsService->getDeathLeaderboard(25)),
            'server_name' => config('zomboid.server_name', 'Project Zomboid Server'),
        ]);
    }
}
