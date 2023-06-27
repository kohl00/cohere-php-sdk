<?php declare(strict_types=1);

namespace Cohere;

/**
 * Represents a Cohere API endpoint request payload.
 */
class Payload {
    private array $payload;

    /**
     * Constructs a new PayloadClass instance.
     *
     * @param array $payload The payload data.
     */
    public function __construct(array $payload = []) {
        $this->payload = $payload;
    }

    /**
     * Gets the payload data.
     *
     * @param bool $clean Whether or not to clean the payload by removing null values.
     * @return array The payload data.
     */
    public function getPayload(bool $clean = FALSE): array {
        if ($clean) {
            $this->cleanPayload();
        }
        return $this->payload;
    }

    /**
     * Sets the payload data.
     *
     * @param array $payload The new payload data.
     */
    public function setPayload(array $payload): Payload {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Cleans the payload by removing null values.
     *
     * @return array The cleaned payload data.
     */
    public function cleanPayload(): Payload {
        $this->payload = array_filter($this->payload, function ($value) {
            return !is_null($value);
        });
        return $this;
    }
}
