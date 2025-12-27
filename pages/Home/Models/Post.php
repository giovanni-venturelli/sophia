<?php
namespace App\Pages\Home\Models;

use Sophia\Database\Entity;

class Post extends Entity
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'id';
}
