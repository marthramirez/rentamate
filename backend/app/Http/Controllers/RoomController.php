<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'status' => ['nullable', 'in:active,maintenance,inactive'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Room::query()->with('property')->withOccupancy()->orderBy('room_number');

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search) {
                $builder->where('room_number', 'like', $search)
                    ->orWhere('floor', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if ($request->boolean('available')) {
            $query->where('status', Room::STATUS_ACTIVE)
                ->whereHas('property', fn ($property) => $property->where('status', 'active'))
                ->whereRaw(
                    'capacity > (select count(*) from room_assignments where room_assignments.room_id = rooms.id and room_assignments.status in (?, ?))',
                    [RoomAssignment::STATUS_ACTIVE, RoomAssignment::STATUS_RESERVED]
                );
        }

        if ($request->boolean('all')) {
            return $this->respond($query->limit(500)->get());
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $room = Room::create($this->validated($request));

        return $this->respond($this->present($room), 'Room created.', 201);
    }

    public function show(Room $room): JsonResponse
    {
        $presented = $this->present($room);
        $presented->load(['assignments' => function ($query) {
            $query->with('lease.tenant')->orderByDesc('move_in_date')->orderByDesc('id')->limit(100);
        }]);

        return $this->respond($presented);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $data = $this->validated($request, $room);

        $updated = DB::transaction(function () use ($room, $data) {
            $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $taken = RoomAssignment::query()
                ->where('room_id', $locked->id)
                ->whereIn('status', [RoomAssignment::STATUS_ACTIVE, RoomAssignment::STATUS_RESERVED])
                ->count();

            if ((int) $data['capacity'] < $taken) {
                throw ValidationException::withMessages([
                    'capacity' => "Capacity cannot be lower than the current held spaces ({$taken}).",
                ]);
            }

            $locked->update($data);

            return $locked;
        });

        return $this->respond($this->present($updated), 'Room updated.');
    }

    public function destroy(Room $room): JsonResponse
    {
        $hasHistory = $room->assignments()->exists() || $room->leases()->exists();

        if ($hasHistory) {
            $room->update(['status' => Room::STATUS_INACTIVE]);

            return $this->respond(
                $this->present($room),
                'Room has occupancy or lease history, so it was deactivated instead of deleted.'
            );
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted.',
            'deleted' => true,
        ]);
    }

    private function present(Room $room): Room
    {
        return Room::query()->with('property')->withOccupancy()->findOrFail($room->id);
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('rooms', 'room_number')
                    ->where(fn ($query) => $query->where('property_id', $request->input('property_id')))
                    ->ignore($room?->id),
            ],
            'floor' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:9999'],
            'monthly_rate' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:active,maintenance,inactive'],
        ]);

        $data['status'] = $data['status'] ?? Room::STATUS_ACTIVE;

        return $data;
    }
}
