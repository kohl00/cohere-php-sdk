<?php declare(strict_types=1);

namespace Cohere;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Represents a Cohere API endpoint request payload.
 */
class Payload implements IteratorAggregate {
    private array $payload = [];

    /**
     * Constructs a new Payload instance.
     *
     * @param array $payload The payload data.
     */
    public function __construct(array $payload = []) {
        $this->payload = $payload;
    }


    /**
     * Get an iterator from an ArrayObject instance
     *
     * @return Traversable An iterator for the values in the array.
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->payload);
    }

    /**
     * Gets the payload data.
     *
     * @param bool $clean Whether or not to clean the payload by removing null values.
     * @return array The payload data.
     */
    public function getPayload(bool $clean = false): array {
        if ($clean) {
            $this->cleanPayload();
        }
        return $this->payload;
    }

    /**
     * Sets the payload data.
     *
     * @param array $payload The new payload data.
     * @return self
     */
    public function setPayload(array $payload): self {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Cleans the payload by removing null values.
     *
     * @return self
     */
    private function cleanPayload(): array {
        $this->payload = array_filter($this->payload, fn($value) => !is_null($value));
        return $this->payload;
    }
}
