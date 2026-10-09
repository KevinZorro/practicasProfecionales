<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Sustitucion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al docente que reemplaza en una sesión (RF73). */
final class SustitucionDocenteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Sustitucion $sustitucion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Le asignaron una sesión por sustitución');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.novedades.sustitucion-docente');
    }
}
