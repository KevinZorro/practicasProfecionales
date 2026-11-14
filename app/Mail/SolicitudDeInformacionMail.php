<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SolicitudInformacion;
use App\Support\EsperaEntreReintentos;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * La solicitud de información de un taller, para el correo de contacto del
 * laboratorio (D8). Sale de la cuenta del laboratorio, como todos los
 * correos, y responder le contesta directamente al interesado.
 */
final class SolicitudDeInformacionMail extends Mailable
{
    use EsperaEntreReintentos;
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SolicitudInformacion $solicitud,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->solicitud->email, $this->solicitud->nombre)],
            subject: 'Información sobre el taller: '.$this->solicitud->taller->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.talleres.solicitud-de-informacion',
        );
    }
}
