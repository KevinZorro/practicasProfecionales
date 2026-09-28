<?php

declare(strict_types=1);

use App\Services\ConfiguracionLandingService;

/*
 * Solo lo que el proyecto cambia de la configuración de Livewire; el resto
 * sigue con sus valores por defecto. Laravel mezcla este archivo con el del
 * paquete clave por clave de primer nivel, así que temporary_file_upload va
 * completo.
 */
return [

    /*
     * Toda subida de archivos pasa primero por aquí, antes de que el
     * formulario la valide. El tope por defecto de Livewire (12 MB) no
     * alcanza para el video del hero (RF11). Queda 1 MB por encima del tope
     * de ese video a propósito: si coincidieran, un archivo apenas más
     * grande se rechazaría aquí, sin mensaje, en vez de en el formulario,
     * que sí dice cuál es el tope. Cada campo valida su propio tipo y tamaño.
     */
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:'.(ConfiguracionLandingService::TAMANO_MAXIMO_VIDEO_KB + 1024)],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
