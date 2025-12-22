<?php
namespace App\Pages\Home\Models;

use App\Database\Entity;

class Post extends Entity
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'id';
}
