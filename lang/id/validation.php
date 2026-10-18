<?php

// Indonesian validation messages for the rules this app uses.
// Anything not listed here falls back to Laravel's English defaults.

return [
    'alpha_dash' => ':Attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'boolean' => ':Attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'email' => ':Attribute harus berupa alamat email yang valid.',
    'file' => ':Attribute harus berupa file.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'max' => [
        'file' => ':Attribute maksimal :max KB.',
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute maksimal :max karakter.',
    ],
    'min' => [
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'mimes' => ':Attribute harus berupa file :values.',
    'numeric' => ':Attribute harus berupa angka.',
    'password' => [
        'letters' => ':Attribute harus berisi minimal satu huruf.',
        'mixed' => ':Attribute harus berisi huruf besar dan huruf kecil.',
        'numbers' => ':Attribute harus berisi minimal satu angka.',
        'symbols' => ':Attribute harus berisi minimal satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di internet. Pilih kata sandi lain.',
    ],
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute ini sudah dipakai.',
    'url' => ':Attribute harus berupa link yang valid, diawali https://.',

    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'password' => 'kata sandi',
        'title' => 'judul',
        'original_url' => 'link produk',
        'slug' => 'slug',
        'status' => 'status',
        'description' => 'deskripsi',
        'og_title' => 'judul preview',
        'og_description' => 'teks preview',
        'og_image_url' => 'link gambar',
        'price' => 'harga',
        'discount_tag' => 'label promo',
        'category' => 'kategori',
        'username' => 'username',
        'display_name' => 'nama yang ditampilkan',
        'bio' => 'bio',
        'avatar_url' => 'link foto profil',
        'productInput' => 'produk',
        'sub_id' => 'Sub ID',
        'report' => 'file laporan',
        'platform' => 'toko',
    ],
];
