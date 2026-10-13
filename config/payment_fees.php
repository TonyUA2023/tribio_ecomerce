<?php

/*
|--------------------------------------------------------------------------
| Comisiones de pasarelas de pago (simulador del formulario de productos)
|--------------------------------------------------------------------------
|
| Solo informativo: Tribio no cobra ni cambia nada con estas cifras, únicamente calcula
| "cuánto recibo" / "cuánto debo cobrar" para el dueño de la tienda. Cada combinación es
| medio de pago + plazo de acreditación, con:
|   percent → comisión porcentual sobre el cobro (3.49 = 3,49 %)
|   fixed   → monto fijo por cobro, en la moneda del país
|   (el IGV se suma sobre la comisión completa: percent + fixed)
|
| Mercado Pago cambia sus tarifas y las ajusta por vendedor: se copian del "Simulador de
| comisiones" de la cuenta de Mercado Pago (Herramientas de ventas → Simulador de comisiones)
| y se agregan aquí. Una combinación que no esté listada simplemente no se ofrece.
|
*/

return [
    'mercado_pago' => [
        'PE' => [
            'currency' => 'PEN',
            'symbol'   => 'S/',
            'igv'      => 18, // %
            'methods'  => [
                'debit' => [
                    'label'   => 'Tarjeta de débito',
                    'release' => [
                        // Verificado con el simulador de Mercado Pago Perú (Checkout, 2026-10):
                        // recibir S/25,00 → cobrar S/27,30 (comisión S/2,30).
                        'instant' => ['label' => 'En el momento', 'percent' => 3.49, 'fixed' => 1.00],
                    ],
                ],
            ],
        ],
    ],
];
