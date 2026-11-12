<?php

namespace App\Exceptions;

use RuntimeException;

/** Une moto n'a qu'une vignette par année : la réimprimer plutôt que la délivrer deux fois. */
class VignetteAlreadyIssuedException extends RuntimeException
{
    public function __construct(public readonly int $year)
    {
        parent::__construct("La vignette {$year} de cette moto a déjà été délivrée : réimprimez-la depuis la liste.");
    }
}
