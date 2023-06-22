<?php declare(strict_types=1);

namespace Cohere;

use Cohere\Errors\CohereConnectionError;

/**
 * This class represents the configuration for the Cohere API.
 */
final class Config {
    /**
     * The API Key for the Cohere API
     *
     * @var string|null
     */
    private $apiKey;
    
    /**
     * The Base URL for the Cohere API
     *
     * @var string|null
     */
    private $apiBaseUrl;
    
    /**
     * The version for the Cohere API.
     *
     * @var string|null
     */
    private $apiVersion;

    /**
     * Config constructor
     *
     * The constructor reads the necessary environment variables, validates them,
     * and then assigns them to the instance variables. If any variables are missing,
     * it throws a CohereConnectionError.
     *
     * @throws CohereConnectionError If any of the necessary environment variables are missing
     */
    public function __construct() {
        $apiKey = $_ENV['COHERE_API_KEY'] ?? null;
        $apiBaseUrl = $_ENV['COHERE_BASE_URL'] ?? null;
        $apiVersion = $_ENV['COHERE_VERSION'] ?? null;

        if (!$apiKey || !$apiBaseUrl || !$apiVersion) {
            throw new CohereConnectionError('Missing required environment variables. Please set COHERE_API_KEY, COHERE_BASE_URL, and COHERE_VERSION.');
        }

        $this->apiKey = $apiKey;
        $this->apiBaseUrl = $apiBaseUrl;
        $this->apiVersion = $apiVersion;
    }

    /**
     * Get the API Key
     *
     * @return string|null The API Key
     */
    public function getApiKey(): ?string {
        return $this->apiKey;
    }

    /**
     * Get the API Base URL
     *
     * @return string|null The API Base URL
     */
    public function getApiBaseUrl(): ?string {
        return $this->apiBaseUrl;
    }

    /**
     * Get the API Version
     *
     * @return string|null The API Version
     */
    public function getVersion(): ?string {
        return $this->apiVersion;
    }
}