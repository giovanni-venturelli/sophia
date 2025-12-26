<?php

use App\Pages\About\AboutComponent;

// Export dei CHILDREN della sezione "About".
// Questo file deve restituire un array di rotte figlie, da includere così:
// [
//   'path' => 'about',
//   'component' => \App\Pages\About\AboutLayoutComponent::class,
//   'children' => include __DIR__ . '/routes.php',
// ]
return [
    [
        'path' => '',
        'component' => AboutComponent::class,
        'data' => [
            'title' => 'About Us',
            'description' => 'Welcome to our application',
        ],
    ],
];