<?php declare(strict_types=1);

namespace Cohere;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Errors\{CohereError, CohereAPIError, CohereConnectionError};
use Cohere\Config;
/*
Cohere Client.
*/
class Client {
    private $client;
    private $requestFactory;
    private $streamFactory;
    private $config;
    protected $batch_size;


    function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        $config
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->batch_size = 96;
    }

    /**
    * Send a request to cohere's API.
    *
    * @param string $endpoint The endpoint to hit
    * @param array|null $json The json body of the request
    * @param string $method The HTTP method for the request
    * @param bool $stream Whether to stream the response
    *
    * @return array The JSON decoded response
    *
    * @throws CohereConnectionError If there's a network error
    * @throws CohereError If there's a client error
    * @throws CohereAPIError If there's an error with the response
    */
    public function request(string $endpoint, ?array $json = null, string $method = "POST", bool $stream = false): array {
        $headers = [
            "Authorization" => "Bearer " . $this->config->getApiKey(),
            "Content-Type" => "application/json",
            "Request-Source" => "php-sdk",
        ];
        
        $url = $this->config->getApiBaseUrl() . '/' . $this->config->getVersion() . '/' . $endpoint;
        try {
            $request = $this->requestFactory->createRequest($method, $url)
                ->withBody($this->streamFactory->createStream(json_encode($json)));
    
            foreach ($headers as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
            
            $response = $this->client->sendRequest($request);
        } catch (Psr\Http\Client\NetworkExceptionInterface $e) {
            throw new CohereConnectionError($e->getMessage(), 0, $e);
        } catch (Psr\Http\Client\ClientExceptionInterface $e) {
            throw new CohereError('Unexpected exception (' . get_class($e) . '): ' . $e->getMessage(), 0, $e);
        }
    
        $bodyContents = $response->getBody()->getContents();
        $json_response = json_decode($bodyContents, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new CohereAPIError("Failed to decode JSON body: " . $bodyContents, 0);
        }        
        $this->checkResponse($json_response, $response->getHeaders(), $response->getStatusCode());
        return $json_response;
    }

    function checkResponse($json_response, $headers, $status_code) {
        if (array_key_exists("X-API-Warning", $headers)) {
            // Log $headers["X-API-Warning"];
        }
        if (array_key_exists("message", $json_response)) { // has errors
            throw new CohereAPIError(
                $json_response["message"],
                $status_code,
                $headers
            );
        }
        if ($status_code >= 400 && $status_code < 500) {
            throw new CohereAPIError(
                "Unexpected client error (status {$status_code}): " . json_encode($json_response),
                $status_code,
                $headers
            );
        }
        if ($status_code >= 500) {
            throw new CohereError("Unexpected server error (status {$status_code}): " . json_encode($json_response));
        }
    }     

    function generate(
        string $prompt,
        object $prompt_vars = null,
        ?string $model = null,
        ?string $preset = null,
        ?int $num_generations = null,
        ?int $max_tokens = null,
        ?float $temperature = null,
        ?int $k = null,
        ?float $p = null,
        ?float $frequency_penalty = null,
        ?float $presence_penalty = null,
        ?array $end_sequences = null,
        ?array $stop_sequences = null,
        ?string $return_likelihoods = null,
        ?string $truncate = null,
        array $logit_bias = [],
        bool $stream = false
    ) {
    
        $json = [
            "max_tokens" => $max_tokens,
            "end_sequences" => $end_sequences,
            "stop_sequences" => $stop_sequences,
            "return_likelihoods" => $return_likelihoods,
            "truncate" => $truncate,
            "prompt" => $prompt,
            "num_generations" => $num_generations,
            "temperature" => $temperature,
            "k" => $k,
            "p" => $p,
            "model" => $model,
            "frequency_penalty" => $frequency_penalty,
            "presence_penalty" => $presence_penalty
        ];
        return $this->request("generate", $json, "POST");
    }

    public function chat(
        string $query,
        string $conversation_id = "",
        ?string $model = null,
        bool $return_chatlog = false,
        bool $return_prompt = false,
        bool $return_preamble = false,
        array $chatlog_override = [],
        array $chat_history = [],
        ?string $preamble_override = null,
        ?string $user_name = null,
        float $temperature = 0.8,
        ?int $max_tokens = null,
        bool $stream = false
    ){
        $json = [
            "query" => $query,
            "conversation_id" => $conversation_id,
            "model" => $model,
            "return_chatlog" => $return_chatlog,
            "return_prompt" => $return_prompt,
            "return_preamble" => $return_preamble,
            "chatlog_override" => $chatlog_override,
            "chat_history" => $chat_history,
            "preamble_override" => $preamble_override,
            "user_name" => $user_name,
            "temperature" => $temperature,
            "max_tokens" => $max_tokens
        ];

        return $this->request("chat", $json, "POST");
    }

    public function embed(
        array $texts,
        ?string $model = null,
        ?string $truncate = null,
        ?bool $compress = false,
        ?string $compression_codebook = "default"
    ): array {
        $responses = [
            "embeddings" => [],
            "compressed_embeddings" => [],
        ];

        $json_bodys = [];
    
        for ($i = 0; $i < count($texts); $i += $this->batch_size) {
            $texts_batch = array_slice($texts, $i, $i + $this->batch_size);
            array_push($json_bodys, [
                "model" => $model,
                "texts" => $texts_batch,
                "truncate" => $truncate,
                "compress" => $compress,
                "compression_codebook" => $compression_codebook,
            ]);
        }
    
        $meta = null;
        foreach ($json_bodys as $json_body) {
            $result = $this->request("embed", $json_body, "POST");
            array_push($responses["embeddings"], ...$result["embeddings"]);
            if (isset($result["compressed_embeddings"])) {
                array_push($responses["compressed_embeddings"], ...$result["compressed_embeddings"]);
            }
            if (!$meta) {
                $meta = $result["meta"];
            }
        }
    
        return [
            "embeddings" => $responses["embeddings"],
            "compressed_embeddings" => $responses["compressed_embeddings"],
            "meta" => $meta
        ];
    }

    public function classify(
        array $inputs = [],
        ?string $model = null,
        ?string $preset = null,
        array $examples = [],
        ?string $truncate = null
    ): array {
        $examples_dicts = [];
        foreach ($examples as $example) {
            $examples_dicts[] = ["text" => $example['text'], "label" => $example['label']];
        }
    
        $json_body = [
            "model" => $model,
            "preset" => $preset,
            "inputs" => $inputs,
            "examples" => $examples_dicts,
            "truncate" => $truncate,
        ];
    
        $response = $this->request("classify", $json_body, "POST");
    
        $classifications = [];
        foreach ($response["classifications"] as $res) {
            $labelObj = [];
            foreach ($res["labels"] as $label => $prediction) {
                $labelObj[$label] = $prediction["confidence"];
            }
            $classifications[] = [
                "input" => $res["input"],
                "prediction" => $res["prediction"],
                "confidence" => $res["confidence"],
                "labels" => $labelObj,
                "id" => $res["id"]
            ];
        }
    
        return [
            "classifications" => $classifications,
            "meta" => $response["meta"] ?? null,
        ];
    }
    
    public function summarize(
        string $text,
        string $model = null,
        string $length = null,
        string $format = null,
        float $temperature = null,
        string $additional_command = null,
        string $extractiveness = null
    ) {
        $json_body = [
            "model" => $model,
            "text" => $text,
            "length" => $length,
            "format" => $format,
            "temperature" => $temperature,
            "additional_command" => $additional_command,
            "extractiveness" => $extractiveness,
        ];
        // remove null values from the array
        $json_body = array_filter($json_body, function ($value) {
            return !is_null($value);
        });
        $response = $this->request("summarize", $json_body, "POST");
        // format the response to mimic the python return object
        $summarizeResponse = [
            "id" => $response["id"],
            "summary" => $response["summary"],
            "meta" => $response["meta"]
        ];
        return $summarizeResponse;
    }
    
    
}
