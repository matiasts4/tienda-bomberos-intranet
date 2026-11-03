<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MktQuote;
use App\Models\MktProduct;
use App\Http\Requests\StoreQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class MarketplaceQuoteController extends Controller
{
    public function index(Request $request)
    {
        $query = MktQuote::with(['marketplace', 'items']);

        // Filtro por Marketplace específico
        if ($request->filled('marketplace_id') && $request->marketplace_id !== 'all') {
            $query->where('marketplace_id', $request->marketplace_id);
        }

        // Filtro por Estado (simplificado a 2 estados: unread / read)
        if ($request->filled('status') && $request->status !== 'all') {
            $st = $request->status;
            if ($st === 'unread' || $st === 'pending') {
                $query->whereIn('status', ['pending', 'unread']);
            } elseif ($st === 'read' || $st === 'reviewed') {
                $query->whereIn('status', ['reviewed', 'read', 'responded', 'closed']);
            } else {
                $query->where('status', $st);
            }
        }

        // Búsqueda por N° Cotización, comprador, email, rut o unidad
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'like', "%{$search}%")
                  ->orWhere('buyer_name', 'like', "%{$search}%")
                  ->orWhere('buyer_email', 'like', "%{$search}%")
                  ->orWhere('buyer_rut', 'like', "%{$search}%")
                  ->orWhere('company_unit', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 50);
        $quotes = $query->latest()->paginate($perPage);

        return response()->json($quotes);
    }

    public function show($id)
    {
        $quote = MktQuote::with(['marketplace', 'items'])->findOrFail($id);
        return response()->json($quote);
    }

    public function update(Request $request, $id)
    {
        $quote = MktQuote::findOrFail($id);

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,unread,reviewed,read,responded,closed,rejected',
            'internal_notes' => 'nullable|string',
        ]);

        $quote->update($validated);

        return response()->json($quote->load(['marketplace', 'items']));
    }

    public function destroy($id)
    {
        $quote = MktQuote::findOrFail($id);

        DB::transaction(function () use ($quote) {
            $quote->items()->delete();
            $quote->delete();
        });

        return response()->json(['message' => 'Cotización eliminada exitosamente', 'id' => (int)$id]);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:mkt_quotes,id',
        ]);

        DB::transaction(function () use ($validated) {
            \App\Models\MktQuoteItem::whereIn('quote_id', $validated['ids'])->delete();
            MktQuote::whereIn('id', $validated['ids'])->delete();
        });

        return response()->json([
            'message' => count($validated['ids']) . ' cotizaciones eliminadas exitosamente',
            'deleted_ids' => $validated['ids']
        ]);
    }

    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:mkt_quotes,id',
            'status' => 'required|string|in:pending,unread,reviewed,read,responded,closed,rejected',
        ]);

        MktQuote::whereIn('id', $validated['ids'])->update(['status' => $validated['status']]);

        return response()->json([
            'message' => count($validated['ids']) . ' cotizaciones actualizadas exitosamente',
            'updated_ids' => $validated['ids'],
            'status' => $validated['status']
        ]);
    }

    public function store(StoreQuoteRequest $request)
    {
        // Marketplace dealer endpoint
        $marketplace = $request->marketplace;

        try {
            DB::beginTransaction();

            $quoteNumber = $this->generateQuoteNumber($marketplace);
            
            $quote = MktQuote::create([
                'marketplace_id' => $marketplace->id,
                'quote_number' => $quoteNumber,
                'buyer_name' => $request->buyer_name,
                'buyer_rut' => $request->buyer_rut,
                'buyer_email' => $request->buyer_email,
                'buyer_phone' => $request->buyer_phone,
                'company_unit' => $request->company_unit,
                'address' => $request->address,
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            foreach ($request->items as $itemData) {
                $productId = $itemData['product_id'] ?? null;
                $sku = $itemData['sku'] ?? null;
                $product = null;

                if ($productId) {
                    $product = $marketplace->products()
                        ->where('mkt_products.id', $productId)
                        ->wherePivot('is_visible', true)
                        ->first();
                }

                if (!$product && $sku) {
                    $product = $marketplace->products()
                        ->where('mkt_products.sku', $sku)
                        ->wherePivot('is_visible', true)
                        ->first();
                }

                if (!$product) {
                    throw new \Exception("El producto seleccionado (" . ($sku ?? $productId) . ") no está disponible en este portal.", 422);
                }
                
                $quote->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $product->price ?? 0,
                    'quantity' => $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            DB::commit();

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://intranettb.com'));
            $portalUrl = rtrim($frontendUrl, '/') . '/m/' . $marketplace->slug;
            $buyerEmail = $request->buyer_email;

            // Enviar notificaciones por correo de forma síncrona para asegurar su ejecución en hosting compartido (Hostinger)
            $this->sendQuoteEmails($marketplace, $quote, $portalUrl, $buyerEmail);

            return response()->json([
                'message' => 'Cotización enviada exitosamente',
                'quote_number' => $quote->quote_number
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json(['message' => 'Error al procesar la cotización', 'error' => $e->getMessage()], $statusCode);
        }
    }

    private function parseEmails(?string $raw): array
    {
        if (empty($raw)) return [];
        $parts = preg_split('/[\s,;]+/', trim((string)$raw), -1, PREG_SPLIT_NO_EMPTY);
        $valid = [];
        foreach ($parts as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }
        return array_values(array_unique($valid));
    }

    private function sendQuoteEmails($marketplace, $quote, string $portalUrl, ?string $buyerEmail)
    {
        // 1. Correo para el distribuidor / administración del marketplace (soporte multi-correo)
        try {
            $rawDest = !empty($marketplace->quote_email) ? $marketplace->quote_email : $marketplace->dealer_email;
            $destinatarios = $this->parseEmails($rawDest);
            $bccDestinatarios = $this->parseEmails($marketplace->internal_email ?? '');

            if (!empty($destinatarios)) {
                $mail = \Illuminate\Support\Facades\Mail::to($destinatarios);
                if (!empty($bccDestinatarios)) {
                    $mail->bcc($bccDestinatarios);
                }
                $mail->send(new \App\Mail\MktQuoteCreatedMail($quote, $portalUrl));
            } else {
                \Illuminate\Support\Facades\Log::warning("Marketplace {$marketplace->name} (ID {$marketplace->id}) no tiene email de cotización configurado.");
            }
        } catch (\Exception $mailEx) {
            \Illuminate\Support\Facades\Log::error("Error enviando correo de cotización al distribuidor: " . $mailEx->getMessage(), [
                'exception' => $mailEx,
                'quote_id' => $quote->id,
                'marketplace_id' => $marketplace->id,
            ]);
        }

        // 2. Correo de confirmación para el cliente final (comprador)
        try {
            if (!empty($buyerEmail)) {
                \Illuminate\Support\Facades\Mail::to(trim($buyerEmail))
                    ->send(new \App\Mail\MktQuoteCustomerConfirmationMail($quote, $portalUrl));
            }
        } catch (\Exception $mailEx2) {
            \Illuminate\Support\Facades\Log::error("Error enviando correo de confirmación al cliente: " . $mailEx2->getMessage(), [
                'exception' => $mailEx2,
                'quote_id' => $quote->id,
            ]);
        }
    }

    private function generateQuoteNumber($marketplace): string
    {
        $abbrev = $this->generateMarketplaceAbbreviation($marketplace->name ?? '', $marketplace->slug ?? '');
        $dayMonth = date('dm'); // formato día y mes: ej. 1808

        // Correlativo de cotizaciones para esta compañía / marketplace
        $count = MktQuote::where('marketplace_id', $marketplace->id)->count() + 1;
        $correlative = str_pad($count, 3, '0', STR_PAD_LEFT);
        $quoteNumber = "{$abbrev}-{$dayMonth}-{$correlative}";

        // Asegurar unicidad global en caso improbable de colisión
        while (MktQuote::where('quote_number', $quoteNumber)->exists()) {
            $count++;
            $correlative = str_pad($count, 3, '0', STR_PAD_LEFT);
            $quoteNumber = "{$abbrev}-{$dayMonth}-{$correlative}";
        }

        return $quoteNumber;
    }

    private function generateMarketplaceAbbreviation(string $name, string $slug = ''): string
    {
        // Normalizar texto y remover ordinales
        $clean = str_replace(['ª', 'º', '°', '.'], '', $name);
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $clean);
        $clean = strtoupper(trim($clean));

        $prefixNum = '';
        if (preg_match('/^(\d+)/', $clean, $numMatch)) {
            $prefixNum = $numMatch[1];
        }

        $stopWords = ['CUERPO', 'DE', 'DEL', 'LA', 'EL', 'LOS', 'LAS', 'BOMBEROS', 'COMPANIA', 'CIA', 'BRIGADA', 'PORTAL', 'DISTRIBUIDOR'];
        $words = array_values(array_filter(explode(' ', $clean), function ($w) use ($stopWords) {
            return !empty($w) && !in_array($w, $stopWords) && !is_numeric($w);
        }));

        if (empty($words)) {
            $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper($slug ?: $name));
            return str_pad(substr($raw, 0, 3), 3, 'X');
        }

        if ($prefixNum !== '') {
            $mainLetters = '';
            foreach ($words as $w) {
                $mainLetters .= substr($w, 0, 1);
            }
            $code = $prefixNum . substr($mainLetters, 0, 2);
            return str_pad(substr($code, 0, 3), 3, '0');
        }

        if (count($words) >= 3) {
            return substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1);
        }

        if (count($words) === 2) {
            $first = substr($words[0], 0, 1);
            $second = substr($words[1], 0, 2);
            return $first . $second;
        }

        $word = $words[0];

        $knownMap = [
            'SANTIAGO' => 'STG',
            'VALPARAISO' => 'VLP',
            'IQUIQUE' => 'IQQ',
            'CONCEPCION' => 'CCP',
            'TALCAHUANO' => 'THN',
            'TEMUCO' => 'TEM',
            'VALDIVIA' => 'VAL',
            'RANCAGUA' => 'RCA',
            'CHILLAN' => 'CHL',
            'COIHUECO' => 'COI',
            'COQUIMBO' => 'COQ',
            'CALAMA' => 'CAL',
            'ANTOFAGASTA' => 'ANT',
            'ARICA' => 'ARI',
            'TALCA' => 'TAL',
            'CURICO' => 'CUR',
            'LINARES' => 'LIN',
            'OSORNO' => 'OSO',
            'NUNOA' => 'NUN',
            'MAIPU' => 'MAI',
        ];

        if (isset($knownMap[$word])) {
            return $knownMap[$word];
        }

        return str_pad(substr($word, 0, 3), 3, 'X');
    }
}
