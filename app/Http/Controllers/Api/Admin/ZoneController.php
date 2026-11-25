<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = Zone::withCount('centers')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:zones,name',
            'code' => 'required|string|max:20|unique:zones,code',
        ]);

        $zone = Zone::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Zone created successfully',
            'data' => $zone,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $zone = Zone::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:zones,name,' . $zone->id,
            'code' => 'required|string|max:20|unique:zones,code,' . $zone->id,
        ]);

        $zone->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Zone updated successfully',
            'data' => $zone,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $zone = Zone::findOrFail($id);

        if ($zone->centers()->exists()) {
            if ($request->boolean('force')) {
                foreach ($zone->centers as $center) {
                    if ($center->registrations()->exists()) {
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot delete zone: Center '{$center->name}' already has registered examination candidates.",
                        ], 422);
                    }
                    $center->rooms()->delete();
                    $center->delete();
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete zone '{$zone->name}' because it contains {$zone->centers()->count()} examination center(s).",
                    'has_centers' => true,
                    'centers_count' => $zone->centers()->count(),
                ], 422);
            }
        }

        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => "Zone '{$zone->name}' deleted successfully.",
        ]);
    }
}
