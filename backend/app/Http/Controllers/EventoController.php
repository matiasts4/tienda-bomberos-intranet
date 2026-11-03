<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EventoController extends Controller
{
    /**
     * GET /api/eventos
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([], 401);
        }

        $request->validate(['start' => 'nullable|date', 'end' => 'nullable|date|after:start']);
        $start = $request->query('start');
        $end = $request->query('end');

        if (!$start || !$end) {
            return response()->json([], 200);
        }

        $start = Carbon::parse($start)->toDateString();
        $end = Carbon::parse($end)->toDateString();

        return response()->json(\App\Support\AgendaFeed::events($user, $start, $end));

    }

    /**
     * POST /api/eventos
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'inicio' => 'required|date',
            'fin' => 'nullable|date|after_or_equal:inicio',
            'tipo' => 'nullable|string',
            'categoria' => 'nullable|string|max:255',
            'nivel' => 'nullable|string|in:usuario,area,corporativo',
            'area' => 'nullable|string',
            'rrule' => 'nullable|string|max:4000',
            'is_all_day' => 'nullable|boolean',
            'color' => 'nullable|string',
            'eventable_id' => 'nullable|integer',
            'eventable_type' => 'nullable|string',
            'invitados' => 'nullable|array',
            'invitados.*' => 'integer|exists:users,id',
        ]);

        $this->validateRecurrence($data);

        $user = Auth::user();
        $nivelFinal = $data['nivel'] ?? 'usuario';
        if ($nivelFinal === 'corporativo' && $user->role !== 'superadmin') $nivelFinal = 'area';

        $evento = Evento::create([
            'user_id' => $user->id,
            'ejecutivo_id' => $user->id, // Compatibilidad
            'titulo' => $data['titulo'],
            'categoria' => $data['categoria'] ?? 'General',
            'descripcion' => $data['descripcion'] ?? null,
            'inicio' => $data['inicio'],
            'fin' => $data['fin'] ?? null,
            'tipo' => $data['tipo'] ?? 'reunion',
            'color' => $data['color'] ?? null,
            'nivel' => $nivelFinal,
            'area' => $data['area'] ?? $user->role,
            'rrule' => $data['rrule'] ?? null,
            'is_all_day' => $data['is_all_day'] ?? false,
            'eventable_id' => $data['eventable_id'] ?? null,
            'eventable_type' => $data['eventable_type'] ?? null,
        ]);

        if (isset($data['invitados'])) {
            $evento->invitados()->sync($data['invitados']);
        }
        
        $evento->load('invitados:id,name');

        return response()->json($evento, 201);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $query = Evento::where('id', $id);
        if ($user->role !== 'superadmin') {
            $query->where(fn($q) => $q->where('user_id', $user->id)->orWhere('ejecutivo_id', $user->id));
        }
        $evento = $query->firstOrFail();

        $data = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'inicio' => 'sometimes|date',
            'fin' => 'nullable|date|after_or_equal:inicio',
            'tipo' => 'sometimes|string',
            'color' => 'nullable|string',
            'rrule' => 'nullable|string|max:4000',
            'is_all_day' => 'nullable|boolean',
            'invitados' => 'nullable|array',
            'invitados.*' => 'integer|exists:users,id',
        ]);

        $this->validateRecurrence(array_merge($evento->only(['inicio', 'rrule']), $data));
        // Arrastrar una serie mueve su ancla; nunca deja DTSTART apuntando al día anterior.
        if (isset($data['inicio']) && $evento->rrule && !array_key_exists('rrule', $data)) {
            $rule = preg_replace('/^DTSTART[^\r\n]*[\r\n]+/m', '', $evento->rrule);
            $data['rrule'] = 'DTSTART:' . Carbon::parse($data['inicio'])->format('Ymd\THis') . "\n" . (str_starts_with($rule, 'RRULE:') ? $rule : 'RRULE:' . $rule);
        }
        $evento->update(collect($data)->except('invitados')->all());
        
        if ($request->has('invitados')) {
            $evento->invitados()->sync($data['invitados'] ?? []);
        }
        
        $evento->load('invitados:id,name');
        
        return response()->json($evento);
    }

    private function validateRecurrence(array $data): void
    {
        $rule = $data['rrule'] ?? null;
        if (!$rule) return;
        $error = null;
        if (!preg_match('/(?:^|RRULE:|;)FREQ=(DAILY|WEEKLY|MONTHLY|YEARLY|HOURLY|MINUTELY|SECONDLY)(?:;|$)/', trim($rule))) {
            $error = 'La regla de repetición no es válida.';
        }
        if (preg_match('/(?:^|;)COUNT=([^;\r\n]+)/', $rule, $m) && (!ctype_digit($m[1]) || (int) $m[1] < 1 || (int) $m[1] > 365)) {
            $error = 'La repetición admite entre 1 y 365 ocurrencias.';
        }
        if (preg_match('/(?:^|;)UNTIL=([^;\r\n]+)/', $rule, $m)) {
            try {
                $until = Carbon::createFromFormat('Ymd\THis\Z', $m[1], 'UTC');
                if ($until->format('Ymd\THis\Z') !== $m[1] || $until->lt(Carbon::parse($data['inicio']))) $error = 'El límite de repetición debe ser igual o posterior al inicio.';
            } catch (\Throwable $e) { $error = 'El límite de repetición no es válido.'; }
        }
        if ($error) throw \Illuminate\Validation\ValidationException::withMessages(['rrule' => $error]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Unauthenticated'], 401);

        $evento = Evento::find($id);
        if (!$evento) {
            return response()->json(['error' => 'Evento no encontrado: ' . $id], 404);
        }

        // Seguridad: Solo SuperAdmin o el Dueño pueden borrar
        $isOwner = ($evento->user_id == $user->id || $evento->ejecutivo_id == $user->id);
        if ($user->role !== 'superadmin' && !$isOwner) {
            return response()->json(['error' => 'No tienes permiso para eliminar este evento'], 403);
        }

        $evento->delete();
        return response()->noContent();
    }
}