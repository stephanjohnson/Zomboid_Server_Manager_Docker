<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\DockerManager;
use App\Services\RconClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function __construct(
        private readonly DockerManager $docker,
        private readonly RconClient $rcon,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $status = $this->docker->getContainerStatus();

        if ($status['running']) {
            return response()->json(['error' => 'Server is already running'], 409);
        }

        $this->docker->startContainer();

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'server.start',
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Server starting']);
    }

    public function stop(Request $request): JsonResponse
    {
        try {
            $this->rcon->connect();
            $this->rcon->command('save');
            sleep(5);
            $this->rcon->command('quit');
        } catch (\Throwable) {
            // RCON unavailable — proceed with Docker stop
        }

        $this->docker->stopContainer(timeout: 30);

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'server.stop',
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Server stopped']);
    }

    public function restart(Request $request): JsonResponse
    {
        try {
            $this->rcon->connect();
            $this->rcon->command('save');
        } catch (\Throwable) {
            // RCON unavailable — proceed with restart
        }

        $this->docker->restartContainer(timeout: 30);

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'server.restart',
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Server restarting']);
    }

    public function save(Request $request): JsonResponse
    {
        try {
            $this->rcon->connect();
            $this->rcon->command('save');
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to save: '.$e->getMessage(),
            ], 503);
        }

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'server.save',
            ip: $request->ip(),
        );

        return response()->json(['message' => 'World saved']);
    }
}
