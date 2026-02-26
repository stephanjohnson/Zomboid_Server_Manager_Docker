<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\RconClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RconController extends Controller
{
    public function __construct(
        private readonly RconClient $rcon,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/rcon');
    }

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:500',
        ]);

        $command = $validated['command'];

        try {
            $this->rcon->connect();
            $response = $this->rcon->command($command);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'RCON failed: '.$e->getMessage(),
                'command' => $command,
            ], 503);
        }

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'rcon.execute',
            target: $command,
            details: ['response_length' => strlen($response)],
            ip: $request->ip(),
        );

        return response()->json([
            'command' => $command,
            'response' => $response,
        ]);
    }
}
