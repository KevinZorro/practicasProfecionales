<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\EsperaEntreReintentos;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** El enlace para elegir una contraseña nueva (ADR 0011). */
final class RecuperarContrasenaMail extends Mailable
{
    use EsperaEntreReintentos;
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $destinatario,
        public readonly string $enlace,
        public readonly int $minutosDeVigencia,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recupera tu contraseña');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.usuarios.recuperar-contrasena');
    }
}
