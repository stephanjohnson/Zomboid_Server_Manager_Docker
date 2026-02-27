<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GiveItemRequest;
use App\Http\Requests\Admin\RemoveItemRequest;
use App\Services\AuditLogger;
use App\Services\DeliveryQueueManager;
use App\Services\InventoryReader;
use App\Services\ItemCatalogReader;
use App\Services\ItemIconResolver;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryReader $inventoryReader,
        private readonly DeliveryQueueManager $deliveryQueue,
        private readonly ItemIconResolver $iconResolver,
        private readonly ItemCatalogReader $catalogReader,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display a player's inventory with icons, catalog, and delivery status.
     */
    public function show(string $username): Response
    {
        $inventory = $this->inventoryReader->getPlayerInventory($username);

        // Resolve icon paths for inventory items
        $items = [];
        if ($inventory && isset($inventory['items'])) {
            foreach ($inventory['items'] as $item) {
                $item['icon'] = $this->iconResolver->resolve($item['full_type']);
                $items[] = $item;
            }
        }

        // Load item catalog for "Give Item" autocomplete
        $catalog = array_map(fn (array $item) => [
            ...$item,
            'icon' => $this->iconResolver->resolve($item['full_type']),
        ], $this->catalogReader->getAll());

        // Get delivery queue + results filtered for this player
        $deliveries = $this->getPlayerDeliveries($username);

        return Inertia::render('admin/player-inventory', [
            'username' => $username,
            'inventory' => $inventory ? [
                'username' => $inventory['username'],
                'timestamp' => $inventory['timestamp'],
                'weight' => $inventory['weight'],
                'max_weight' => $inventory['max_weight'],
                'items' => $items,
            ] : null,
            'catalog' => $catalog,
            'deliveries' => $deliveries,
        ]);
    }

    /**
     * Queue a "give" action for a player.
     */
    public function giveItem(GiveItemRequest $request, string $username): JsonResponse
    {
        $validated = $request->validated();

        $entry = $this->deliveryQueue->giveItem(
            $username,
            $validated['item_type'],
            $validated['count'],
        );

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'inventory.give',
            target: $username,
            details: [
                'item_type' => $validated['item_type'],
                'count' => $validated['count'],
                'delivery_id' => $entry['id'],
            ],
            ip: $request->ip(),
        );

        return response()->json($entry, 201);
    }

    /**
     * Queue a "remove" action for a player.
     */
    public function removeItem(RemoveItemRequest $request, string $username): JsonResponse
    {
        $validated = $request->validated();

        $entry = $this->deliveryQueue->removeItem(
            $username,
            $validated['item_type'],
            $validated['count'],
        );

        $this->auditLogger->log(
            actor: $request->user()->name ?? 'admin',
            action: 'inventory.remove',
            target: $username,
            details: [
                'item_type' => $validated['item_type'],
                'count' => $validated['count'],
                'delivery_id' => $entry['id'],
            ],
            ip: $request->ip(),
        );

        return response()->json($entry, 201);
    }

    /**
     * Get delivery queue + results for a player (JSON endpoint for polling).
     */
    public function deliveryStatus(string $username): JsonResponse
    {
        return response()->json($this->getPlayerDeliveries($username));
    }

    /**
     * Filter queue entries and results for a specific player.
     *
     * @return array{pending: array, results: array}
     */
    private function getPlayerDeliveries(string $username): array
    {
        $queue = $this->deliveryQueue->readQueue();
        $results = $this->deliveryQueue->readResults();

        $pending = array_values(array_filter(
            $queue['entries'] ?? [],
            fn (array $entry) => $entry['username'] === $username
        ));

        $playerResults = array_values(array_filter(
            $results['results'] ?? [],
            function (array $result) use ($username, $queue) {
                // Match results to queue entries by ID to filter by username
                foreach ($queue['entries'] ?? [] as $entry) {
                    if ($entry['id'] === $result['id'] && $entry['username'] === $username) {
                        return true;
                    }
                }

                return false;
            }
        ));

        return [
            'pending' => $pending,
            'results' => $playerResults,
        ];
    }
}
