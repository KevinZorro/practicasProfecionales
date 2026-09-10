<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SolicitudAprobadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Solicitud $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Su solicitud de escenario fue aprobada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes.aprobada',
        );
    }
}
