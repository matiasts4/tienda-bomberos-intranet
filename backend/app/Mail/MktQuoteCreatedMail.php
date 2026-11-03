<?php

namespace App\Mail;

use App\Models\MktQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MktQuoteCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;
    public $portalUrl;

    public function __construct(MktQuote $quote, string $portalUrl = '')
    {
        if ($quote->exists) {
            $this->quote = $quote->loadMissing(['marketplace', 'items']);
        } else {
            $this->quote = $quote;
        }
        if (empty($portalUrl)) {
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://intranettb.com'));
            $portalUrl = rtrim($frontendUrl, '/') . '/m/' . ($this->quote->marketplace->slug ?? 'default');
        }
        $this->portalUrl = $portalUrl;
    }

    public function envelope(): Envelope
    {
        $quoteNumber = $this->quote->quote_number ?? 'S/N';
        $buyerName = $this->quote->buyer_name ?? 'Cliente';
        return new Envelope(
            subject: "Nueva Cotización {$quoteNumber} - {$buyerName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $grouped = [];
        $grandTotal = 0;
        $totalUnits = 0;
        $items = $this->quote->items ?? collect();

        foreach ($items as $item) {
            $productName = $item->product_name ?? 'Producto';
            $key = $item->product_id ? (string)$item->product_id : $productName;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $productName,
                    'unit_price' => floatval($item->unit_price ?? 0),
                    'total_qty' => 0,
                    'entries' => [],
                ];
            }
            $quantity = intval($item->quantity ?? 1);
            $grouped[$key]['total_qty'] += $quantity;
            $grouped[$key]['entries'][] = [
                'quantity' => $quantity,
                'notes' => $item->notes,
            ];
        }

        $formatPrice = function ($amount) {
            if (!$amount || $amount <= 0) return '-';
            return '$ ' . number_format($amount, 0, ',', '.');
        };

        $itemsHtml = '';
        foreach ($grouped as $group) {
            $rowTotal = $group['unit_price'] * $group['total_qty'];
            $grandTotal += $rowTotal;
            $totalUnits += $group['total_qty'];

            $hasMultiple = count($group['entries']) > 1;
            $detailsHtml = '';

            if ($hasMultiple) {
                $detailsHtml .= "<div style='margin-top: 6px;'>";
                foreach ($group['entries'] as $entry) {
                    $noteText = $entry['notes'] ? htmlspecialchars($entry['notes']) : 'Opción estándar';
                    $detailsHtml .= "<div style='margin-bottom: 3px; font-size: 12px; color: #475569;'>
                        <span style='display: inline-block; background-color: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 8px; font-weight: 600;'>
                            🏷️ {$noteText}
                        </span>
                        <span style='margin-left: 6px; font-weight: bold; color: #0f172a;'>&times; {$entry['quantity']}</span>
                    </div>";
                }
                $detailsHtml .= "</div>";
            } else {
                $entry = $group['entries'][0];
                if (!empty($entry['notes'])) {
                    $noteText = htmlspecialchars($entry['notes']);
                    $detailsHtml .= "<div style='margin-top: 4px;'>
                        <span style='display: inline-block; background-color: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 8px; font-size: 12px; color: #475569; font-weight: 500;'>
                            📝 {$noteText}
                        </span>
                    </div>";
                }
            }

            $nameHtml = htmlspecialchars($group['name']);
            $itemsHtml .= "<tr>
                <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #0f172a; vertical-align: top;'>
                    <div style='font-weight: 600; color: #0f172a;'>{$nameHtml}</div>
                    {$detailsHtml}
                </td>
                <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: center; font-size: 14px; font-weight: bold; color: #0f172a; vertical-align: top;'>
                    {$group['total_qty']}
                </td>
                <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: right; font-size: 13px; color: #475569; vertical-align: top;'>
                    {$formatPrice($group['unit_price'])}
                </td>
                <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: right; font-size: 14px; font-weight: bold; color: #0f172a; vertical-align: top;'>
                    {$formatPrice($rowTotal)}
                </td>
            </tr>";
        }

        $formattedGrandTotal = $grandTotal > 0 ? '$ ' . number_format($grandTotal, 0, ',', '.') : '-';
        $mktName = htmlspecialchars($this->quote->marketplace?->name ?? 'Tienda Bomberos Marketplace');
        $quoteNum = htmlspecialchars($this->quote->quote_number ?? 'S/N');
        $buyerName = htmlspecialchars($this->quote->buyer_name ?? 'Cliente');
        $buyerRut = htmlspecialchars($this->quote->buyer_rut ?? '-');
        $buyerEmail = htmlspecialchars($this->quote->buyer_email ?? '');
        $buyerPhone = htmlspecialchars($this->quote->buyer_phone ?? '-');
        $companyUnit = htmlspecialchars($this->quote->company_unit ?? '-');
        $address = htmlspecialchars($this->quote->address ?? '-');
        $createdAt = $this->quote->created_at?->format('d/m/Y H:i') ?? '-';
        $logoUrl = $this->resolveAbsoluteImageUrl($this->quote->marketplace?->logo_url);
        $portalUrl = htmlspecialchars($this->portalUrl);
        $logoHtml = !empty($logoUrl)
            ? "<img src='{$logoUrl}' alt='{$mktName}' width='56' style='display:block;width:56px;max-width:56px;height:auto;border:0;' />"
            : "<div style='font-size:18px;line-height:22px;font-weight:800;color:#ffffff;'>TB</div>";
        $notesHtml = $this->quote->notes
            ? "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:24px;background-color:#fffbeb;border:1px solid #fde68a;border-radius:10px;'><tr><td style='padding:16px 18px;'><div style='font-size:12px;line-height:16px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:#92400e;'>Observaciones del cliente</div><div style='margin-top:6px;font-size:14px;line-height:21px;color:#78350f;'>" . nl2br(htmlspecialchars($this->quote->notes)) . "</div></td></tr></table>"
            : '';

        return "
        <!doctype html><html><body style='margin:0;padding:0;background-color:#f3f4f6;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='width:100%;background-color:#f3f4f6;'><tr><td align='center' style='padding:28px 12px;'>
        <table role='presentation' width='680' cellpadding='0' cellspacing='0' style='width:100%;max-width:680px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:14px;font-family:system-ui,-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;'>
            <tr><td style='padding:0;background-color:#111827;border-radius:14px 14px 0 0;'>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0'><tr>
                    <td width='88' valign='middle' style='padding:24px 0 24px 28px;'>{$logoHtml}</td>
                    <td valign='middle' style='padding:24px 12px;'><div style='font-size:11px;line-height:15px;font-weight:800;letter-spacing:0.09em;color:#fca5a5;'>NUEVA COTIZACIÓN RECIBIDA</div><div style='margin-top:5px;font-size:20px;line-height:26px;font-weight:750;color:#ffffff;'>{$mktName}</div><div style='margin-top:3px;font-size:12px;line-height:18px;color:#9ca3af;'>Recibida el {$createdAt}</div></td>
                    <td align='right' valign='middle' style='padding:24px 28px 24px 8px;'><span style='display:inline-block;padding:8px 12px;background-color:#dc2626;border-radius:999px;font-size:12px;line-height:16px;font-weight:800;color:#ffffff;white-space:nowrap;'>N° {$quoteNum}</span></td>
                </tr></table>
            </td></tr>
            <tr><td style='padding:28px;'>
                <div style='font-size:16px;line-height:22px;font-weight:750;color:#111827;'>Ficha del comprador</div>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:14px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;'>
                    <tr><td width='50%' valign='top' style='padding:16px 18px;border-bottom:1px solid #e5e7eb;'><div style='font-size:11px;line-height:15px;font-weight:700;text-transform:uppercase;color:#6b7280;'>Nombre</div><div style='margin-top:4px;font-size:14px;line-height:20px;font-weight:700;color:#111827;'>{$buyerName}</div></td><td width='50%' valign='top' style='padding:16px 18px;border-left:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;'><div style='font-size:11px;line-height:15px;font-weight:700;text-transform:uppercase;color:#6b7280;'>RUT</div><div style='margin-top:4px;font-size:14px;line-height:20px;font-weight:700;color:#111827;'>{$buyerRut}</div></td></tr>
                    <tr><td width='50%' valign='top' style='padding:16px 18px;border-bottom:1px solid #e5e7eb;'><div style='font-size:11px;line-height:15px;font-weight:700;text-transform:uppercase;color:#6b7280;'>Correo</div><div style='margin-top:4px;font-size:14px;line-height:20px;font-weight:600;word-break:break-word;'><a href='mailto:{$buyerEmail}' style='color:#b91c1c;text-decoration:none;'>{$buyerEmail}</a></div></td><td width='50%' valign='top' style='padding:16px 18px;border-left:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;'><div style='font-size:11px;line-height:15px;font-weight:700;text-transform:uppercase;color:#6b7280;'>Teléfono</div><div style='margin-top:4px;font-size:14px;line-height:20px;font-weight:700;color:#111827;'>{$buyerPhone}</div></td></tr>
                    <tr><td width='50%' valign='top' style='padding:16px 18px;'><div style='font-size:11px;line-height:15px;font-weight:700;text-transform:uppercase;color:#6b7280;'>Compañía / Unidad</div><div style='margin-top:4px;font-size:14px;line-height:20px;font-weight:700;color:#111827;'>{$companyUnit}</div></td><td width='50%' valign='top' style='padding:16px 18px;border-left:1px solid #e5e7eb;'><div style='font-size:11px;line-height:15px;font-weight:700;text-transform:uppercase;color:#6b7280;'>Dirección</div><div style='margin-top:4px;font-size:14px;line-height:20px;font-weight:700;color:#111827;'>{$address}</div></td></tr>
                </table>

                <div style='margin-top:28px;font-size:16px;line-height:22px;font-weight:750;color:#111827;'>Productos solicitados</div>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:14px;width:100%;border-collapse:collapse;border:1px solid #e5e7eb;'>
                <thead>
                    <tr style='background-color:#f3f4f6;text-align:left;'><th style='padding:11px 10px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;'>Producto / Opciones</th><th style='padding:11px 8px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;text-align:center;width:52px;'>Cant.</th><th style='padding:11px 8px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;text-align:right;width:92px;'>Unitario</th><th style='padding:11px 10px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;text-align:right;width:92px;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
                <tfoot>
                    <tr style='background-color:#111827;'><td style='padding:14px 10px;font-size:11px;font-weight:800;color:#d1d5db;text-transform:uppercase;'>Total de unidades</td><td style='padding:14px 8px;text-align:center;font-size:15px;font-weight:800;color:#ffffff;'>{$totalUnits}</td><td style='padding:14px 8px;text-align:right;font-size:11px;font-weight:800;color:#d1d5db;text-transform:uppercase;'>TOTAL ESTIMADO</td><td style='padding:14px 10px;text-align:right;font-size:16px;font-weight:800;color:#fca5a5;white-space:nowrap;'>{$formattedGrandTotal}</td></tr>
                </tfoot>
                </table>
                {$notesHtml}
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:28px;'><tr><td align='center'><a href='{$portalUrl}' style='display:inline-block;padding:13px 22px;background-color:#dc2626;border-radius:8px;font-size:14px;line-height:20px;font-weight:800;color:#ffffff;text-decoration:none;'>Abrir portal del marketplace</a></td></tr></table>
            </td></tr>
            <tr><td style='padding:20px 28px;background-color:#f9fafb;border-top:1px solid #e5e7eb;border-radius:0 0 14px 14px;text-align:center;'><div style='font-size:12px;line-height:18px;color:#6b7280;'>Notificación operativa generada automáticamente por <strong style='color:#374151;'>Tienda Bomberos</strong>.</div><div style='margin-top:3px;font-size:11px;line-height:16px;color:#9ca3af;'>Utiliza los datos de esta ficha para contactar al comprador y procesar su solicitud.</div></td></tr>
        </table>
        </td></tr></table></body></html>
        ";
    }

    private function resolveAbsoluteImageUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return str_replace(' ', '%20', $url);
        }

        $appUrl = rtrim(config('app.url', env('APP_URL', 'https://seagreen-snake-487241.hostingersite.com')), '/');
        $cleanPath = ltrim($url, '/');

        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        $parts = explode('/', $cleanPath);
        $encodedParts = array_map('rawurlencode', $parts);
        $encodedPath = implode('/', $encodedParts);

        return "{$appUrl}/api/storage-proxy/{$encodedPath}";
    }
}
