<?php

return [
    'required' => ':attribute wajib diisi.',
    'email' => 'Format :attribute tidak benar.',
    'numeric' => ':attribute harus berupa angka.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'boolean' => ':attribute hanya boleh benar atau salah.',
    'array' => ':attribute harus berupa daftar.',
    'date' => ':attribute bukan tanggal yang sah.',
    'image' => ':attribute harus berupa gambar.',
    'file' => ':attribute harus berupa berkas.',
    'mimes' => ':attribute harus berformat: :values.',
    'unique' => ':attribute sudah dipakai.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'not_in' => ':attribute yang dipilih tidak sah.',
    'in' => ':attribute yang dipilih tidak sah.',
    'string' => ':attribute harus berupa teks.',
    'max' => [
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'file' => ':attribute tidak boleh lebih dari :max kilobita.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
        'array' => ':attribute tidak boleh lebih dari :max item.',
    ],
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'file' => ':attribute minimal :min kilobita.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal :min item.',
    ],
    'between' => [
        'numeric' => ':attribute harus antara :min dan :max.',
        'file' => ':attribute harus antara :min dan :max kilobita.',
        'string' => ':attribute harus antara :min dan :max karakter.',
        'array' => ':attribute harus berisi antara :min dan :max item.',
    ],
    'custom' => [
        'attribute-name' => [
            'rule-name' => '',
        ],
    ],
    'attributes' => [

    ],
];
