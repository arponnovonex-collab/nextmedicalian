<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\ExamRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Center::with('zone')->withCount('rooms');

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $centers = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $centers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:centers,code',
            'address' => 'required|string',
            'google_maps_url' => 'nullable|url',
            'google_maps_embed_url' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capacity' => 'required|integer|min:1',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'special_instructions' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Auto-generate standard Google Maps share URL if not supplied but address exists
        if (empty($validated['google_maps_url'])) {
            $validated['google_maps_url'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($validated['name'] . ', ' . $validated['address']);
        }
        if (empty($validated['google_maps_embed_url'])) {
            $validated['google_maps_embed_url'] = 'https://maps.google.com/maps?q=' . urlencode($validated['name'] . ', ' . $validated['address']) . '&t=&z=15&ie=UTF8&iwloc=&output=embed';
        }

        $center = Center::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Center created successfully',
            'data' => $center->load('zone'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $center = Center::with(['zone', 'rooms'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $center,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $center = Center::findOrFail($id);

        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:centers,code,' . $center->id,
            'address' => 'required|string',
            'google_maps_url' => 'nullable|url',
            'google_maps_embed_url' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capacity' => 'required|integer|min:1',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'special_instructions' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $center->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Center updated successfully',
            'data' => $center->load('zone'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $center = Center::findOrFail($id);

        if ($center->registrations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete center because it has registered examinees.',
            ], 422);
        }

        $center->rooms()->delete();
        $center->delete();

        return response()->json([
            'success' => true,
            'message' => 'Center deleted successfully',
        ]);
    }

    public function rooms(int $centerId): JsonResponse
    {
        $rooms = ExamRoom::where('center_id', $centerId)->get();

        return response()->json([
            'success' => true,
            'data' => $rooms,
        ]);
    }

    public function addRoom(Request $request, int $centerId): JsonResponse
    {
        $center = Center::findOrFail($centerId);

        $validated = $request->validate([
            'room_number' => 'required|string|max:50',
            'floor' => 'nullable|string|max:50',
            'capacity' => 'required|integer|min:1',
        ]);

        $existing = ExamRoom::where('center_id', $center->id)
            ->where('room_number', $validated['room_number'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A room with this number already exists in this center.',
            ], 422);
        }

        $validated['center_id'] = $center->id;
        $room = ExamRoom::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room added successfully',
            'data' => $room,
        ], 201);
    }

    public function deleteRoom(int $centerId, int $roomId): JsonResponse
    {
        $room = ExamRoom::where('center_id', $centerId)->where('id', $roomId)->firstOrFail();

        if ($room->registrations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete room with allocated examinees.',
            ], 422);
        }

        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room deleted successfully',
        ]);
    }
}
