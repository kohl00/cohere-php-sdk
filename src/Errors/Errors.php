<?php

namespace Cohere\Errors;

use Exception;

class CohereError extends Exception {
    private $message;

    public function __construct($message = null) {
        parent::__construct($message);
        $this->message = $message;
    }

    public function __toString() {
        $msg = $this->message ?? "<empty message>";
        return $msg;
    }
}

class CohereAPIError extends CohereError {
    private $http_status;
    private $headers;

    public function __construct($message = null, $http_status = null, $headers = null) {
        parent::__construct($message);
        $this->http_status = $http_status;
        $this->headers = $headers ?? [];
    }

    public static function fromResponse($response, $message = null) {
        return new self($message ?? $response->getBody()->getContents(), $response->getStatusCode(), $response->getHeaders());
    }
}

class CohereConnectionError extends CohereError{}
