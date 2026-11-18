<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:active,inactive'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Property::query()->withCount('rooms')->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', $search)
                    ->orWhere('address', 'like', $search)
                    ->orWhere('contact_number', 'like', $search);
            });
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $property = Property::create($data);

        return $this->respond($property->loadCount('rooms'), 'Property created.', 201);
    }

    public function show(Property $property): JsonResponse
    {
        $property->load(['rooms' => fn ($query) => $query->withOccupancy()]);
        $property->loadCount('rooms');

        return $this->respond($property);
    }

    public function update(Request $request, Property $property): JsonResponse
    {
        $property->update($this->validated($request));

        return $this->respond($property->fresh()->loadCount('rooms'), 'Property updated.');
    }

    public function destroy(Property $property): JsonResponse
    {
        if ($property->rooms()->exists()) {
            $property->update(['status' => Property::STATUS_INACTIVE]);

            return $this->respond(
                $property->fresh()->loadCount('rooms'),
                'Property has rooms, so it was deactivated instead of deleted.'
            );
        }

        $property->delete();

        return response()->json([
            'message' => 'Property deleted.',
            'deleted' => true,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $data['status'] = $data['status'] ?? Property::STATUS_ACTIVE;

        return $data;
    }
}
