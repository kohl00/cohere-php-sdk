<?php

namespace Cohere;

use Cohere\Errors\CohereConnectionError;

final class Config {
    private $apiKey;
    private $apiBaseUrl;
    private $apiVersion;

    public function __construct() {
        $this->apiKey = $_ENV['COHERE_API_KEY'];
        $this->apiBaseUrl = $_ENV['COHERE_BASE_URL'];
        $this->apiVersion = $_ENV['COHERE_VERSION'];

        if (!$this->apiKey || !$this->apiBaseUrl || !$this->apiVersion) {
            throw new CohereConnectionError('Missing required environment variables. Please set COHERE_API_KEY, COHERE_BASE_URL, and COHERE_VERSION.');
        }
    }

    public function getApiKey() {
        return $this->apiKey;
    }

    public function getApiBaseUrl() {
        return $this->apiBaseUrl;
    }

    public function getVersion() {
        return $this->apiVersion;
    }
}