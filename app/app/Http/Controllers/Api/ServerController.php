<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\BroadcastRequest;
use App\Http\Requests\Api\RestartServerRequest;
use App\Http\Requests\Api\ServerLogsRequest;
use App\Jobs\RestartGameServer;
use App\Jobs\SendServerWarning;
use App\Services\AuditLogger;
use App\Services\DockerManager;
use App\Services\RconClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ServerController
{
    public function __construct(
        private readonly RconClient $rcon,
        private readonly DockerManager $docker,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function status(): JsonResponse
    {
        try {
            $containerStatus = $this->docker->getContainerStatus();
        } catch (\Throwable) {
            $containerStatus = ['running' => false];
        }
        $online = $containerStatus['running'] ?? false;

        $result = [
            'online' => $online,
            'player_count' => 0,
            'players' => [],
            'uptime' => null,
            'map' => null,
            'max_players' => null,
        ];

        if ($online) {
            $result['uptime'] = $this->calculateUptime($containerStatus['started_at'] ?? null);

            try {
                $this->rcon->connect();
                $playersResponse = $this->rcon->command('players');
                $parsed = $this->parsePlayers($playersResponse);
                $result['player_count'] = $parsed['count'];
                $result['players'] = $parsed['names'];
            } catch (\Throwable) {
                // RCON unavailable — server may still be starting
            }

            $iniData = $this->readServerIni();
            $result['map'] = $iniData['Map'] ?? null;
            $result['max_players'] = isset($iniData['MaxPlayers']) ? (int) $iniData['MaxPlayers'] : null;
        }

        return response()->json($result);
    }

    public function start(): JsonResponse
    {
        try {
            $status = $this->docker->getContainerStatus();
        } catch (\Throwable) {
            return response()->json([
                'error' => 'Cannot connect to Docker daemon',
            ], 503);
        }

        if ($status['running']) {
            return response()->json([
                'error' => 'Server is already running',
            ], 409);
        }

        try {
            $this->docker->startContainer();
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to start server',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Server starting',
        ]);
    }

    public function stop(): JsonResponse
    {
        try {
            $this->rcon->connect();
            $this->rcon->command('save');
            sleep(5);
            $this->rcon->command('quit');
        } catch (\Throwable) {
            // RCON unavailable — proceed with Docker stop
        }

        try {
            $this->docker->stopContainer(timeout: 30);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to stop server',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Server stopped',
        ]);
    }

    public function restart(RestartServerRequest $request): JsonResponse
    {
        $countdown = $request->validated('countdown');
        $message = $request->validated('message');

        if ($countdown) {
            $warningMessage = $message ?? "Server restarting in {$countdown} seconds";

            try {
                $this->rcon->connect();
                $this->rcon->command("servermsg \"{$warningMessage}\"");
            } catch (\Throwable) {
                // RCON unavailable — still schedule the restart
            }

            RestartGameServer::dispatch($request->ip())
                ->delay(now()->addSeconds($countdown));

            SendServerWarning::dispatchCountdownWarnings($countdown, 'restarting', 'server.pending_action:restart');

            return response()->json([
                'message' => "Server restart scheduled in {$countdown} seconds",
                'countdown' => $countdown,
            ]);
        }

        try {
            $this->rcon->connect();
            $this->rcon->command('save');
        } catch (\Throwable) {
            // RCON unavailable — proceed with restart
        }

        try {
            $this->docker->restartContainer(timeout: 30);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to restart server',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Server restarting',
        ]);
    }

    public function save(): JsonResponse
    {
        try {
            $this->rcon->connect();
            $this->rcon->command('save');
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to save: server may be offline',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'World saved',
        ]);
    }

    public function broadcast(BroadcastRequest $request): JsonResponse
    {
        $message = $request->validated('message');

        try {
            $this->rcon->connect();
            $this->rcon->command("servermsg \"{$message}\"");
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to broadcast: server may be offline',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'message' => 'Broadcast sent',
        ]);
    }

    public function logs(ServerLogsRequest $request): JsonResponse
    {
        $tail = $request->validated('tail', 100);
        $since = $request->validated('since');

        $sinceTimestamp = $since ? (string) Carbon::parse($since)->timestamp : null;

        try {
            $lines = $this->docker->getContainerLogs($tail, $sinceTimestamp);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Cannot retrieve logs: Docker daemon unavailable',
                'detail' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'lines' => $lines,
            'count' => count($lines),
        ]);
    }

    /**
     * @return array{count: int, names: string[]}
     */
    private function parsePlayers(string $response): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $response)));
        $names = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '-')) {
                $names[] = ltrim($line, '- ');
            }
        }

        // Extract count from header line like "Players connected (2):"
        $count = count($names);
        if (preg_match('/\((\d+)\)/', $response, $matches)) {
            $count = (int) $matches[1];
        }

        return ['count' => $count, 'names' => $names];
    }

    private function calculateUptime(?string $startedAt): ?string
    {
        if ($startedAt === null) {
            return null;
        }

        try {
            return Carbon::parse($startedAt)->diffForHumans(syntax: true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function readServerIni(): array
    {
        $path = config('zomboid.paths.server_ini');

        if (! is_file($path)) {
            return [];
        }

        $data = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            if (str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value);
        }

        return $data;
    }
}
