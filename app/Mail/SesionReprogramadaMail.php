<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Reprogramacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al docente de que su sesión cambió (RF61). */
final class SesionReprogramadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Reprogramacion $reprogramacion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Su sesión fue reprogramada');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.novedades.sesion-reprogramada');
    }
}
