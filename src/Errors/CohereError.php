<?php declare(strict_types=1);

namespace Cohere\Errors;

use Exception;

class CohereError extends Exception {
    public function __toString(): string {
        return $this->message;
    }
}