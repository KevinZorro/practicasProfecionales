<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/*
 * S-03 del informe de seguridad: el documento de identidad no se guarda en
 * claro. Se cifra con APP_KEY, así que una copia de la base sin la clave no
 * lo revela.
 */

it('guarda el documento cifrado y lo lee en claro', function (): void {
    $cuenta = User::factory()->create(['documento' => '1020304050']);

    $guardado = (string) DB::table('users')->where('id', $cuenta->id)->value('documento');

    expect($guardado)->not->toContain('1020304050')
        ->and(Crypt::decryptString($guardado))->toBe('1020304050')
        ->and($cuenta->fresh()->documento)->toBe('1020304050');
});

it('admite una cuenta sin documento', function (): void {
    $cuenta = User::factory()->create(['documento' => null]);

    expect(DB::table('users')->where('id', $cuenta->id)->value('documento'))->toBeNull()
        ->and($cuenta->fresh()->documento)->toBeNull();
});

