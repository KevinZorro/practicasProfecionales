<?php

declare(strict_types=1);

// Los caracteres y no la entidad HTML: la vista de Livewire la escapa en
// aria-label y el lector de pantalla leía «&laquo; Anterior» (QA-12).
return [

    'previous' => '« Anterior',
    'next' => 'Siguiente »',

];
