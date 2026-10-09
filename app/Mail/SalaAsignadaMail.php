<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Preparacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SalaAsignadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Preparacion $preparacion,
        public readonly bool $esCambio,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->esCambio ? 'Cambió la sala de su sesión' : 'Sala asignada para su sesión',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.preparaciones.sala-asignada',
        );
    }
}
