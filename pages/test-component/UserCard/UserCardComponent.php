<?php
namespace App\Pages\TestComponent\UserCard;

use App\Component\Component;
use App\Component\Input;

#[Component(
    selector: 'app-user-card',
    template: __DIR__ . '/UserCardComponent.html'
)]
class UserCardComponent
{
    #[Input]
    public string $name;

    #[Input]
    public int $age;
}
