<?php
namespace App\Form\Results;

class JsonResult
{
    public function __construct(public array $data, public int $status = 200)
    {
    }
}
