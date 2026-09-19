<?php

namespace App\Exceptions;

use Exception;

class SlideDocumentNotFoundException extends Exception
{
    public function __construct(string $document)
    {
        parent::__construct("The slide document [{$document}] could not be found.");
    }
}
