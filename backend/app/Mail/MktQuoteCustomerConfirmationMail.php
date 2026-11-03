<?php

namespace App\Mail;

use App\Models\MktQuote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MktQuoteCustomerConfirmationMail extends Mailable
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
        return new Envelope(
            subject: "Hemos recibido tu solicitud de cotización N° {$quoteNumber}",
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
        $buyerName = trim($this->quote->buyer_name ?? 'Cliente');
        $firstName = htmlspecialchars(explode(' ', $buyerName)[0]);
        $logoUrl = $this->resolveAbsoluteImageUrl($this->quote->marketplace?->logo_url);
        $portalUrl = htmlspecialchars($this->portalUrl);
        $createdAt = $this->quote->created_at?->format('d/m/Y') ?? '-';
        $logoHtml = !empty($logoUrl)
            ? "<img src='{$logoUrl}' alt='{$mktName}' width='64' style='display:block;width:64px;max-width:64px;height:auto;border:0;margin:0 auto;' />"
            : "<div style='font-size:18px;line-height:22px;font-weight:800;color:#111827;'>TB</div>";
        $rawSupportEmails = (string)($this->quote->marketplace?->quote_email ?: $this->quote->marketplace?->dealer_email ?: '');
        $supportEmails = preg_split('/[\s,;]+/', trim($rawSupportEmails), -1, PREG_SPLIT_NO_EMPTY);
        $supportEmail = collect($supportEmails)->first(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
        $supportHtml = $supportEmail
            ? "Si necesitas ayuda, escríbenos a <a href='mailto:" . htmlspecialchars($supportEmail) . "' style='color:#b91c1c;text-decoration:none;font-weight:700;'>" . htmlspecialchars($supportEmail) . "</a>."
            : "Si necesitas ayuda, comunícate con el equipo responsable de tu Cuerpo de Bomberos.";

        return "
        <!doctype html><html><body style='margin:0;padding:0;background-color:#f4f5f3;'>
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='width:100%;background-color:#f4f5f3;'><tr><td align='center' style='padding:28px 12px;'>
        <table role='presentation' width='680' cellpadding='0' cellspacing='0' style='width:100%;max-width:680px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:14px;font-family:system-ui,-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;'>
            <tr><td align='center' style='padding:30px 28px 10px;'>{$logoHtml}</td></tr>
            <tr><td align='center' style='padding:10px 28px 28px;'>
                <table role='presentation' cellpadding='0' cellspacing='0'><tr><td align='center' valign='middle' style='width:54px;height:54px;background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:999px;font-size:26px;line-height:54px;'>✅</td></tr></table>
                <h1 style='margin:18px 0 0;font-size:26px;line-height:33px;font-weight:800;letter-spacing:-0.02em;color:#111827;'>¡Solicitud Recibida!</h1>
                <p style='margin:10px 0 0;font-size:16px;line-height:24px;color:#374151;'>¡Hola <strong>{$firstName}</strong>!</p>
            </td></tr>
            <tr><td style='padding:0 28px;'>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;'><tr><td style='padding:17px 18px;font-size:14px;line-height:22px;color:#166534;'>Tu solicitud <strong>N° {$quoteNum}</strong> quedó registrada correctamente. El equipo de <strong>{$mktName}</strong> ya puede revisarla y se pondrá en contacto contigo para continuar el proceso.</td></tr></table>

                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:22px;'><tr><td style='font-size:12px;line-height:17px;color:#6b7280;'>Número de solicitud</td><td align='right' style='font-size:12px;line-height:17px;color:#6b7280;'>Fecha</td></tr><tr><td style='padding-top:3px;font-size:15px;line-height:21px;font-weight:800;color:#111827;'>{$quoteNum}</td><td align='right' style='padding-top:3px;font-size:15px;line-height:21px;font-weight:700;color:#111827;'>{$createdAt}</td></tr></table>

                <div style='margin-top:28px;font-size:17px;line-height:23px;font-weight:750;color:#111827;'>Resumen de tu pedido</div>
                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:14px;width:100%;border-collapse:collapse;border:1px solid #e5e7eb;'>
                <thead>
                    <tr style='background-color:#f9fafb;text-align:left;'><th style='padding:11px 10px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;'>Producto / Opciones</th><th style='padding:11px 8px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;text-align:center;width:52px;'>Cant.</th><th style='padding:11px 8px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;text-align:right;width:92px;'>Unitario</th><th style='padding:11px 10px;border-bottom:1px solid #d1d5db;font-size:10px;line-height:14px;color:#4b5563;font-weight:800;text-transform:uppercase;text-align:right;width:92px;'>Subtotal</th></tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
                <tfoot>
                    <tr style='background-color:#f9fafb;border-top:1px solid #d1d5db;'><td style='padding:14px 10px;font-size:11px;font-weight:800;color:#4b5563;text-transform:uppercase;'>Total de unidades</td><td style='padding:14px 8px;text-align:center;font-size:15px;font-weight:800;color:#111827;'>{$totalUnits}</td><td style='padding:14px 8px;text-align:right;font-size:11px;font-weight:800;color:#4b5563;text-transform:uppercase;'>TOTAL ESTIMADO</td><td style='padding:14px 10px;text-align:right;font-size:16px;font-weight:800;color:#111827;white-space:nowrap;'>{$formattedGrandTotal}</td></tr>
                </tfoot>
                </table>

                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='margin-top:28px;'><tr><td align='center'><a href='{$portalUrl}' style='display:inline-block;padding:14px 22px;background-color:#111827;border-radius:8px;font-size:14px;line-height:20px;font-weight:800;color:#ffffff;text-decoration:none;'>Volver a la tienda de {$mktName}</a></td></tr></table>
            </td></tr>
            <tr><td align='center' style='padding:30px 28px;'>
                <div style='font-size:14px;line-height:21px;color:#374151;'>Gracias por confiar en <strong>{$mktName}</strong>.</div>
                <div style='margin-top:7px;font-size:12px;line-height:19px;color:#6b7280;'>{$supportHtml}</div>
            </td></tr>
            <tr><td style='padding:17px 28px;background-color:#111827;border-radius:0 0 14px 14px;text-align:center;font-size:11px;line-height:17px;color:#9ca3af;'>Este correo confirma la recepción de tu solicitud. No constituye una venta ni reserva de stock.</td></tr>
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
