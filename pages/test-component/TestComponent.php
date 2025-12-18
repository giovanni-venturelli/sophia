<?php

namespace App\Pages\TestComponent;

use App\Component\Component;

#[Component(
    selector: 'app-root',
    template: __DIR__ . '/TestComponent.html'
)]
class TestComponent
{
    public array $apps = [
        [
            'title' => 'App 1',
            'users' => [
                ['id' => 1, 'name' => 'Mario', 'age' => 30],
                ['id' => 2, 'name' => 'Luigi', 'age' => 15],
                ['id' => 3, 'name' => 'Giovanni', 'age' => 46]
            ]
        ],
        [
            'title' => 'App 2',
            'users' => [
                ['id' => 4, 'name' => 'Marione', 'age' => 300],
                ['id' => 5, 'name' => 'Luigione', 'age' => 150],
                ['id' => 6, 'name' => 'Giovannone', 'age' => 460]
            ]
        ],
    ];
}