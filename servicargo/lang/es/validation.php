<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'exists' => 'El :attribute seleccionado no existe.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'in' => 'El valor de :attribute no es válido.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'array' => 'El campo :attribute debe ser una lista.',
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'gt' => [
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
    ],
    'gte' => [
        'numeric' => 'El campo :attribute debe ser mayor o igual a :value.',
    ],
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
    ],
    'regex' => 'El formato del campo :attribute no es válido.',

    'attributes' => [
        'ci' => 'CI',
        'nombre' => 'nombre',
        'apellido' => 'apellido',
        'correo' => 'correo',
        'contrasena' => 'contraseña',
        'rol' => 'rol',
        'telefono' => 'teléfono',
        'precio_unitario' => 'precio unitario',
        'categoria_id' => 'categoría',
        'codigo' => 'código',
        'cliente_id' => 'cliente',
        'vendedor_id' => 'vendedor',
        'validez_dias' => 'validez en días',
        'peso_kg' => 'peso',
        'volumen_m3' => 'volumen',
        'tipo_envio' => 'tipo de envío',
        'tipo_pago' => 'tipo de pago',
        'metodo_pago' => 'método de pago',
        'numero_cuotas' => 'número de cuotas',
        'monto' => 'monto',
    ],

    'custom' => [
        'ci' => [
            'regex' => 'El CI debe contener solo dígitos.',
        ],
        'telefono' => [
            'regex' => 'El teléfono debe contener solo dígitos.',
        ],
        'nombre' => [
            'regex' => 'El nombre solo puede contener letras y espacios.',
        ],
        'apellido' => [
            'regex' => 'El apellido solo puede contener letras y espacios.',
        ],
        'contenido' => [
            'regex' => 'El contenido solo puede contener letras y espacios.',
        ],
    ],
];
