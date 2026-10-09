<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso diario de sesiones próximas sin formato intramural (RF60). Uno por
 * persona con todas sus sesiones, no uno por sesión.
 */
final class AvisoFormatoIntramuralMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Solicitud>  $sesiones
     */
    public function __construct(
        public readonly User $destinatario,
        public readonly Collection $sesiones,
        public readonly bool $paraDocente,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sesiones->count() === 1
                ? 'Una sesión próxima no tiene formato intramural'
                : sprintf('%d sesiones próximas no tienen formato intramural', $this->sesiones->count()),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sesiones.aviso-formato-intramural',
        );
    }
}
