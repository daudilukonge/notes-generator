<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class SlidePdfGenerationException extends Exception
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The slide PDF could not be generated.', 0, $previous);
    }
}
