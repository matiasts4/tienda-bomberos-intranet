<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfContent;
    public $totalItems;

    public function __construct($pdfContent, $totalItems)
    {
        $this->pdfContent = $pdfContent;
        $this->totalItems = $totalItems;
    }

    public function build()
    {
        $fecha = now()->format('d/m/Y');

        return $this->subject("🚨 Alerta de Stock Crítico - {$fecha}")
                    ->view('emails.alerta_stock') // OJO: Necesitamos crear esta vista visual
                    ->attachData($this->pdfContent, "Reporte_Critico_{$fecha}.pdf", [
                        'mime' => 'application/pdf',
                    ])
                    ->with([
                        'totalItems' => $this->totalItems,
                        'fecha' => $fecha
                    ]);
    }
}