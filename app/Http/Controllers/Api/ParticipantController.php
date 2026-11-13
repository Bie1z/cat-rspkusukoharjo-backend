<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $query = User::with('unit')
            ->where('role', 'pegawai')
            ->orderBy('name');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($builder) =>
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
            );
        }

        return response()->json([
            'data' => $query->get()->map(
                fn (User $user) => $this->participantData($user)
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $this->validated($request);

        // Status awal pegawai adalah aktif.
        $isActive = $data['is_active'] ?? true;

        unset($data['is_active']);

        $user = User::create([
            ...$data,
            'role' => 'pegawai',
            'password' => $data['nip'],
        ]);

        $user->is_active = $isActive;
        $user->save();

        return response()->json([
            'data' => $this->participantData(
                $user->load('unit')
            ),
        ], 201);
    }

    public function show(
        Request $request,
        User $participant
    ): JsonResponse {
        $this->ensureAdmin($request);
        $this->ensureParticipant($participant);

        return response()->json([
            'data' => $this->participantData(
                $participant->load('unit')
            ),
        ]);
    }

    public function update(
        Request $request,
        User $participant
    ): JsonResponse {
        $this->ensureAdmin($request);
        $this->ensureParticipant($participant);

        $data = $this->validated(
            $request,
            $participant->id
        );

        // Jika status tidak dikirim, pertahankan status lama.
        $isActive = $data['is_active']
            ?? $participant->is_active;

        unset($data['is_active']);

        $participant->update($data);

        // Perubahan status dilakukan secara eksplisit.
        $participant->is_active = $isActive;
        $participant->save();

        return response()->json([
            'data' => $this->participantData(
                $participant->fresh()->load('unit')
            ),
        ]);
    }

    public function destroy(
        Request $request,
        User $participant
    ): JsonResponse {
        $this->ensureAdmin($request);
        $this->ensureParticipant($participant);

        $participant->delete();

        return response()->json(null, 204);
    }

    private function validated(
        Request $request,
        ?int $id = null
    ): array {
        $nipRule = 'unique:users,nip'.($id ? ",{$id}" : '');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => [
                'required',
                'string',
                'regex:/^[0-9]{7}$/',
                $nipRule,
            ],
            'unit_id' => ['required', 'exists:units,id'],
            'position' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email'.($id ? ",{$id}" : ''),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->attributes->get('authUser')?->role === 'admin',
            403,
            'Akses hanya untuk Admin CAT.'
        );
    }

    private function ensureParticipant(User $user): void
    {
        abort_unless(
            $user->role === 'pegawai',
            404,
            'Peserta tidak ditemukan.'
        );
    }

    private function participantData(User $user): array
    {
        return [
            'id' => $user->id,
            'nip' => $user->nip,
            'name' => $user->name,
            'unit_id' => $user->unit_id,
            'unit' => $user->unit?->name,
            'position' => $user->position,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
