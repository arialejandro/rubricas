<?php

return [
    // Permite que nuevas maestras creen su cuenta desde /registro. En producción
    // puede apagarse (RUBRICA_REGISTRATION=false) y dar de alta cuentas a mano.
    'registration' => (bool) env('RUBRICA_REGISTRATION', true),
];
