<?php
namespace Sophia\Form\Results;

class NoContentResult
{
    public function __construct(public int $status = 204)
    {
    }
}
