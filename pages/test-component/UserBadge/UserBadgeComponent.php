<?php

namespace App\Pages\TestComponent\UserBadge;

use App\Component\Component;
use App\Component\Input;

#[Component(
    selector: 'app-user-badge',
    template: __DIR__ . '/UserBadgeComponent.html',
    styles: [__DIR__ . '/UserBadgeComponent.css']
)]
class UserBadgeComponent
{
    #[Input]
    public string $name;

    #[Input]
    public int $age;
}
