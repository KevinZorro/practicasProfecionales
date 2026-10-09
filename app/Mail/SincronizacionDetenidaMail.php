<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al ADMIN de que la sincronización se frenó para no desactivar de más (RF20). */
final class SincronizacionDetenidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly int $porDesactivar,
        public readonly int $activas,
        public readonly int $umbral,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'La sincronización de usuarios se detuvo sin aplicar cambios');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.usuarios.sincronizacion-detenida');
    }
}
