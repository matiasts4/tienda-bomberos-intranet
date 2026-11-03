<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\EncuestaQr;
use App\Models\EncuestaSatisfaccion;
use App\Services\SurveyQuoteSource;
use App\Support\SurveyReference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EncuestaController extends Controller
{
    public function index()
    {
        return response()->json(EncuestaSatisfaccion::with(['cotizacion', 'cliente', 'cotizacion.user', 'vendedor', 'contacto', 'peticion'])
            ->orderByDesc('created_at')->get());
    }

    public function quoteOptions(Request $request)
    {
        $data = $request->validate(['source' => 'required|in:sistema,externa', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim($data['q'] ?? '');
        if ($data['source'] === 'sistema') {
            $query = Cotizacion::with(['cliente', 'cuerpo', 'compania.cuerpo', 'contacto', 'user']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $term = '%'.$search.'%';
                    $q->where('nombre', 'like', $term)->orWhere('numero', 'like', $term);
                    if (ctype_digit(ltrim($search, '#'))) {
                        $q->orWhere('id', (int) ltrim($search, '#'));
                    }
                    foreach (['cliente' => 'nombre_empresa', 'cuerpo' => 'nombre', 'compania' => 'nombre', 'contacto' => 'nombre'] as $relation => $field) {
                        $q->orWhereHas($relation, fn ($r) => $r->where($field, 'like', $term));
                    }
                });
            }
        } else {
            $query = SurveyQuoteSource::externalQuery()->with('user')->whereIn('id', SurveyQuoteSource::externalGroups());
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $term = '%'.$search.'%';
                    $q->where('referencia_externa', 'like', $term)->orWhere('nombre_cotizacion', 'like', $term)
                        ->orWhere('cliente_externo', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term));
                });
            }
        }
        $page = $query->orderByDesc('id')->paginate(20);
        $page->setCollection($page->getCollection()->map(fn ($row) => $data['source'] === 'sistema'
            ? SurveyQuoteSource::describeInternal($row) : SurveyQuoteSource::describeExternal($row)));

        return response()->json($page);
    }

    public function contacts(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:100', 'page' => 'nullable|integer|min:1']);
        $term = '%'.trim($data['q']).'%';
        // Directorio completo: no usa los filtros operativos de Prospección.
        $query = Contacto::with(['cliente', 'cuerpos', 'companias.cuerpo'])->where(function ($q) use ($term) {
            $q->where('nombre', 'like', $term)->orWhere('cargo', 'like', $term)
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre_empresa', 'like', $term))
                ->orWhereHas('cuerpos', fn ($c) => $c->where('nombre', 'like', $term))
                ->orWhereHas('companias', fn ($c) => $c->where('nombre', 'like', $term));
        });
        $page = $query->orderBy('nombre')->orderBy('id')->paginate(20);
        $page->setCollection($page->getCollection()->map(fn ($row) => SurveyQuoteSource::describeContact($row)));

        return response()->json($page);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hash' => 'required|string|max:12000',
            'calidad_producto' => 'required|integer|between:1,5',
            'calidad_personalizacion' => 'required|integer|between:1,5',
            'tiempo_entrega' => 'required|integer|between:1,5',
            'atencion_comercial' => 'required|integer|between:1,5',
            'comentarios' => 'nullable|string|max:10000',
        ]);
        $payload = SurveyReference::decode($data['hash']);
        $ratings = collect($data)->except('hash')->all();
        if (isset($payload['legacy_manual'])) {
            return $this->storeLegacyManual($payload['legacy_manual'], $ratings);
        }

        try {
            return DB::transaction(function () use ($payload, $ratings) {
                // Todas las líneas de una externa bloquean la misma fila canónica.
                $source = SurveyQuoteSource::resolve($payload['source'], $payload['source_id'], true);
                $existing = EncuestaSatisfaccion::where('referencia_clave', $source['key']);
                if ($source['cotizacion_id']) {
                    $existing->orWhere('cotizacion_id', $source['cotizacion_id']);
                }
                if ($existing->exists()) {
                    return $this->alreadyAnswered();
                }
                $qr = isset($payload['qr_id']) ? EncuestaQr::findOrFail($payload['qr_id']) : null;
                if ($qr && $qr->referencia_clave !== $source['key']) {
                    throw ValidationException::withMessages(['hash' => 'El QR no corresponde a esta cotización.']);
                }
                $contactId = array_key_exists('contacto_id', $payload) ? $payload['contacto_id'] : $source['contacto_id'];
                $contact = $contactId ? Contacto::find($contactId) : null;
                $record = EncuestaSatisfaccion::create($ratings + [
                    'origen' => $source['source'], 'referencia_clave' => $source['key'],
                    'cotizacion_id' => $source['cotizacion_id'], 'peticion_id' => $source['peticion_id'],
                    'contacto_id' => $contact?->id,
                    'cliente_id' => $source['cliente_id'] ?: $contact?->cliente_id,
                    'vendedor_id' => $qr ? $qr->vendedor_id : $source['vendedor_id'],
                    'vendedor_nombre' => $qr?->vendedor_nombre ?? $source['vendedor_nombre'],
                    'identificador_venta' => $qr?->referencia ?? $source['referencia'],
                    'nombre_venta' => $payload['nombre_venta'] ?? $source['nombre_venta'],
                    'nombre_cliente_manual' => $payload['cliente_nombre'] ?? $source['cliente_nombre'],
                    'empresa_cliente' => $payload['empresa_cliente'] ?? $source['empresa_cliente'],
                ]);
                EncuestaQr::where('referencia_clave', $source['key'])->update(['encuesta_id' => $record->id, 'respondida_at' => $record->created_at]);

                return response()->json(['message' => 'Evaluación registrada correctamente.', 'encuesta' => $record], 201);
            });
        } catch (UniqueConstraintViolationException) {
            // Otra respuesta concurrente pudo ganar mientras esperábamos el bloqueo.
            return $this->alreadyAnswered();
        }
    }

    private function storeLegacyManual(array $payload, array $ratings)
    {
        $data = Validator::make($payload, [
            'identificador_venta' => 'required|string|max:100', 'vendedor_id' => 'nullable|integer|exists:users,id',
            'vendedor_manual' => 'nullable|string|max:100', 'nombre_cliente_manual' => 'nullable|string|max:255',
            'nombre_venta' => 'nullable|string|max:255',
        ])->validate();
        // Mantiene la semántica de los QR antiguos, sin inventar un vínculo por dígitos.
        $key = 'manual:'.hash('sha256', $data['identificador_venta']);
        try {
            return DB::transaction(function () use ($data, $ratings, $key) {
                if (EncuestaSatisfaccion::where('identificador_venta', $data['identificador_venta'])
                    ->whereNull('cotizacion_id')->whereNull('peticion_id')->exists()) {
                    return $this->alreadyAnswered();
                }
                $record = EncuestaSatisfaccion::create($ratings + $data + ['origen' => 'manual_legacy', 'referencia_clave' => $key]);

                return response()->json(['message' => 'Evaluación registrada correctamente.', 'encuesta' => $record], 201);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->alreadyAnswered();
        }
    }

    private function alreadyAnswered()
    {
        return response()->json(['message' => 'Esta cotización ya fue evaluada. ¡Muchas gracias!'], 409);
    }

    public function generateQr($id)
    {
        $source = SurveyQuoteSource::resolve('sistema', (int) $id);
        $url = $this->surveyUrl(SurveyReference::encode(['source' => 'sistema', 'source_id' => (int) $id]));

        return response()->json([
            'qr_code' => 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(300)->generate($url)),
            'url' => $url, 'is_answered' => EncuestaSatisfaccion::where('cotizacion_id', $source['cotizacion_id'])->exists(),
        ]);
    }

    public function downloadManualQrPdf(Request $request)
    {
        $data = $request->validate([
            'source' => 'required|in:sistema,externa', 'source_id' => 'required|integer|min:1',
            'contacto_id' => 'nullable|integer|exists:contactos,id',
            'cliente_nombre' => 'nullable|string|max:255', 'empresa_cliente' => 'nullable|string|max:255',
        ]);
        $source = SurveyQuoteSource::resolve($data['source'], (int) $data['source_id']);
        $contactId = array_key_exists('contacto_id', $data) ? $data['contacto_id'] : $source['contacto_id'];
        $contact = $contactId ? SurveyQuoteSource::contact((int) $contactId) : null;
        $qrId = (string) Str::uuid();
        $payload = [
            'qr_id' => $qrId,
            'source' => $source['source'], 'source_id' => $source['source_id'],
            'contacto_id' => $contact['id'] ?? null, 'nombre_venta' => $source['nombre_venta'],
            'cliente_nombre' => $contact['nombre'] ?? ($data['cliente_nombre'] ?? $source['cliente_nombre']),
            'empresa_cliente' => $source['empresa_cliente'] ?: ($contact['empresa'] ?? ($data['empresa_cliente'] ?? null)),
        ];
        $token = SurveyReference::encode($payload);
        $url = $this->surveyUrl($token);
        $qrImage = 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(300)->generate($url));
        // Renderizar antes de registrar: una generación fallida no crea un pendiente.
        $contents = Pdf::loadView('pdf.survey_qr', ['source' => $source, 'details' => $payload, 'qrImage' => $qrImage])->output();
        DB::transaction(function () use ($request, $source, $payload, $token, $qrId) {
            SurveyQuoteSource::resolve($source['source'], $source['source_id'], true);
            $existing = EncuestaSatisfaccion::where('referencia_clave', $source['key']);
            if ($source['cotizacion_id']) {
                $existing->orWhere('cotizacion_id', $source['cotizacion_id']);
            }
            $answer = $existing->lockForUpdate()->first();
            EncuestaQr::create([
                'id' => $qrId, 'referencia_clave' => $source['key'], 'origen' => $source['source'],
                'cotizacion_id' => $source['cotizacion_id'], 'peticion_id' => $source['peticion_id'],
                'contacto_id' => $payload['contacto_id'], 'cliente_id' => $source['cliente_id'],
                'referencia' => $source['referencia'], 'nombre_venta' => $payload['nombre_venta'],
                'cliente_nombre' => $payload['cliente_nombre'], 'empresa_cliente' => $payload['empresa_cliente'],
                'vendedor_id' => $source['vendedor_id'], 'vendedor_nombre' => $source['vendedor_nombre'],
                'generado_por_id' => $request->user()->id, 'token' => $token,
                'encuesta_id' => $answer?->id, 'respondida_at' => $answer?->created_at,
            ]);
        });

        return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="QR_Evaluacion_'.$qrId.'.pdf"']);
    }

    public function qrHistory(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:todos,pendiente,respondida', 'page' => 'nullable|integer|min:1']);
        $query = EncuestaQr::with('generadoPor:id,name');
        if (($data['status'] ?? 'todos') === 'pendiente') {
            $query->whereNull('respondida_at');
        }
        if (($data['status'] ?? 'todos') === 'respondida') {
            $query->whereNotNull('respondida_at');
        }
        if (trim($data['q'] ?? '') !== '') {
            $term = '%'.trim($data['q']).'%';
            $query->where(fn ($q) => $q->where('referencia', 'like', $term)->orWhere('nombre_venta', 'like', $term)->orWhere('empresa_cliente', 'like', $term)->orWhere('cliente_nombre', 'like', $term));
        }

        return response()->json($query->orderByDesc('created_at')->orderByDesc('id')->paginate(20));
    }

    public function reprintQr(EncuestaQr $qr)
    {
        $qrImage = 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(300)->generate($this->surveyUrl($qr->token)));
        $contents = Pdf::loadView('pdf.survey_qr', [
            'source' => ['source' => $qr->origen, 'referencia' => $qr->referencia, 'vendedor_nombre' => $qr->vendedor_nombre],
            'details' => ['nombre_venta' => $qr->nombre_venta, 'cliente_nombre' => $qr->cliente_nombre, 'empresa_cliente' => $qr->empresa_cliente],
            'qrImage' => $qrImage,
        ])->output();

        return response($contents, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="QR_Evaluacion_'.$qr->id.'.pdf"']);
    }

    private function surveyUrl(string $reference): string
    {
        return rtrim(config('app.frontend_url'), '/').'/encuesta?ref='.rawurlencode($reference);
    }
}
