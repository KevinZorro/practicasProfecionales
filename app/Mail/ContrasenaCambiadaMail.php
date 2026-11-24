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

/**
 * Aviso de que la contraseña de la cuenta cambió (ADR 0011). Si no fue la
 * persona, es como se entera de que alguien más entró a su correo o a la
 * consola del servidor.
 */
final class ContrasenaCambiadaMail extends Mailable
{
    use EsperaEntreReintentos;
    use Queueable, SerializesModels;

    public function __construct(public readonly User $destinatario) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu contraseña cambió');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.usuarios.contrasena-cambiada');
    }
}
