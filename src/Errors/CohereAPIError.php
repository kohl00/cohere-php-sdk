<?php declare(strict_types=1);

namespace Cohere\Errors;

use Cohere\Errors\CohereError;

class CohereAPIError extends CohereError {
    private ?int $http_status = null;
    private array $headers = [];

    public function __construct(?string $message = null, ?int $http_status = null, ?array $headers = null) {
        parent::__construct($message);
        $this->http_status = $http_status;
        $this->headers = $headers ?? [];
    }

    public static function fromResponse($response, ?string $message = null): self {
        return new self($message ?? $response->getBody()->getContents(), $response->getStatusCode(), $response->getHeaders());
    }
}