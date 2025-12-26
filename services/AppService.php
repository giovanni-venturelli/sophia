<?php

namespace App\Services;
use App\Injector\Injectable;

#[Injectable(providedIn: 'root')]  // Global singleton service
class AppService {
    private array $items = [];

    public function addItems(array $items): void
{
    $this->items = array_merge( $this->items, $items);
}

public function getItems(): array
{
    return $this->items;
}
}