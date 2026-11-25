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

    public function destroy(int $id): JsonResponse
    {
        $zone = Zone::findOrFail($id);

        if ($zone->centers()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete zone because it contains active examination centers.',
            ], 422);
        }

        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Zone deleted successfully',
        ]);
    }
}
