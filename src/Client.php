<?php declare(strict_types=1);

namespace Cohere;

use Cohere\Config;
use Cohere\Endpoints;
use Cohere\Errors\{
    CohereAPIError,
    CohereConnectionError,
    CohereError
};
use Psr\Http\Client\{
    ClientExceptionInterface,
    ClientInterface,
    NetworkExceptionInterface
};
use Psr\Http\Message\{
    RequestFactoryInterface,
    StreamFactoryInterface
};
use Psr\Log\LoggerInterface;
use ReflectionClass;
use InvalidArgumentException;

/*
  The main class to interact with the Cohere API.
*/
class Client {
    // The size of the batch for embedding requests.
    protected const BATCH_SIZE = 96;

    // The HTTP client used to send requests
    private ClientInterface $client;

    // Factory for creating HTTP requests.
    private RequestFactoryInterface $requestFactory;

    // Factory for creating HTTP streams
    private StreamFactoryInterface $streamFactory;

    // Config object storing the settings for the client.
    private Config $config;

    // Logger to log warnings and errors.
    private ?LoggerInterface $logger;

    /**
     * Create a new Cohere client.
     *
     * @param ClientInterface $client The HTTP client used to send requests
     * @param RequestFactoryInterface $requestFactory Factory for creating HTTP requests
     * @param StreamFactoryInterface $streamFactory Factory for creating HTTP streams
     * @param Config $config Config object storing the settings for the client
     * @param LoggerInterface|null $logger Logger to log warnings and errors
     */
    function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        Config $config,
        ?LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->requestFactory = $requestFactory;
        $this->logger = $logger;
        $this->streamFactory = $streamFactory;
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
    public function request(string $endpoint, ?array $json = [], string $method = "POST", bool $stream = false): array {
        try {
            $endpoints = (new ReflectionClass(Endpoints::class))->getConstants();
            if (!in_array($endpoint, $endpoints)) {
                throw new InvalidArgumentException("Invalid endpoint: $endpoint");
            }
            $url = $this->config->getApiBaseUrl() . '/' . $this->config->getVersion() . '/' . $endpoint;
            $request = $this->requestFactory->createRequest($method, $url)
                ->withBody($this->streamFactory->createStream(json_encode($json)));
            
            $headers = [
                "Authorization" => "Bearer " . $this->config->getApiKey(),
                "Content-Type" => "application/json",
                "Request-Source" => "php-sdk",
            ];
            foreach ($headers as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
            
            $response = $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface $e) {
            throw new CohereConnectionError($e->getMessage(), 0, $e);
        } catch (ClientExceptionInterface $e) {
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

    /**
     * Checks if there is a problem with the response received from the request made to Cohere's API.
     * If an error occurs, it throws a custom exception based on the error.
     *
     * @param array $json_response The decoded JSON response
     * @param array $headers The headers of the response
     * @param int $status_code The HTTP status code of the response
     *
     * @throws CohereAPIError If the response contains a message or a client error occurred
     * @throws CohereError If a server error occurred
     */
    function checkResponse($json_response, $headers, $status_code) {
        if (array_key_exists("X-API-Warning", $headers)) {
            if ($this->logger) {
                $this->logger->warning($headers["X-API-Warning"]);
            }
        }
        if (array_key_exists("message", $json_response)) {
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

    /**
     * Makes a generate request to the Cohere API and returns the response.
     *
     * @param string $prompt The text to be completed by the model
     * @param object|null $prompt_vars Variables in the prompt text that can be replaced
     * @param string|null $model The model used for the request
     * @param string|null $preset A preset configuration for the model
     * @param int|null $num_generations The number of different completions to generate
     * @param int|null $max_tokens The maximum number of tokens to generate
     * @param float|null $temperature Controls the randomness of the generated text
     * @param int|null $k The number of tokens considered for each step of the generation
     * @param float|null $p Determines the cumulative probability cutoff for token selection
     * @param float|null $frequency_penalty Penalty for using frequent tokens
     * @param float|null $presence_penalty Penalty for using new tokens
     * @param array|null $end_sequences Sequences that indicate the end of a generated text
     * @param array|null $stop_sequences Sequences that should stop the text generation
     * @param string|null $return_likelihoods Whether to return likelihoods over the vocabulary at each timestep
     * @param string|null $truncate How to truncate the input text
     * @param array $logit_bias Biases the logits before sampling
     * @param bool $stream Whether to stream the response
     *
     * @return array The JSON decoded response
     */
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
    
        $json_body = [
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
        
        $json_body = $this->cleanPayload($json_body);

        return $this->request(Endpoints::GENERATE, $json_body, "POST");
    }

    protected function cleanPayload(array &$payload): array {
        return array_filter($payload, function($value) {
            return !is_null($value);
        });
    }

    /**
     * Makes a chat request to the Cohere API and returns the response.
     *
     * @param string $query The query for the chat
     * @param string $conversation_id The conversation ID for context in the chat
     * @param string|null $model The model used for the request
     * @param bool $return_chatlog Whether to return the chatlog
     * @param bool $return_prompt Whether to return the prompt
     * @param bool $return_preamble Whether to return the preamble
     * @param array $chatlog_override Overrides the chatlog
     * @param array $chat_history The chat history
     * @param string|null $preamble_override Overrides the preamble
     * @param string|null $user_name The name of the user
     * @param float $temperature Controls the randomness of the generated text
     * @param int|null $max_tokens The maximum number of tokens to generate
     * @param bool $stream Whether to stream the response
     *
     * @return array The JSON decoded response
     */
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
        $json_body = [
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
            "max_tokens" => $max_tokens,
            "stream" => $stream,
        ];

        $json_body =$this->cleanPayload($json_body);

        return $this->request(Endpoints::CHAT, $json_body, "POST");
    }

    /**
     * Makes a rerank request to the Cohere API and returns the response.
     *
     * @param string $query The query for reranking
     * @param array $documents The documents to be reranked
     * @param string|null $model The model used for the request
     * @param string|null $query_id The ID of the query
     * @param array|null $doc_ids The IDs of the documents
     * @param bool $stream Whether to stream the response
     *
     * @return array The JSON decoded response
     */
    public function rerank(
        string $query, 
        array $documents, 
        string $model = "rerank-english-v2.0", 
        ?int $top_n = null, 
        ?int $max_chunks_per_doc = null
    ): array {
        $parsed_docs = [];
        foreach ($documents as $doc) {
            if (is_string($doc)) {
                array_push($parsed_docs, ["text" => $doc]);
            } elseif (is_array($doc) && array_key_exists("text", $doc)) {
                array_push($parsed_docs, $doc);
            } else {
                throw new CohereError('Invalid format for documents, must be a list of strings or arrays with a "text" key');
            }
        }

        $json_body = array(
            "query" => $query,
            "documents" => $parsed_docs,
            "model" => $model,
            "top_n" => $top_n,
            "return_documents" => false,
            "max_chunks_per_doc" => $max_chunks_per_doc,
        );

        $json_body = $this->cleanPayload($json_body);
        $reranking = $this->request(Endpoints::RERANK, $json_body, "POST");
        $reranking = $this->rankedResults($reranking);
        foreach ($reranking as $index => $rank) {
            $reranking[$index]['document'] = $parsed_docs[$rank['index']];
        }
        return $reranking;
    }

    function rankedResults(array $response) {
        $results = [];
        foreach ($response['results'] as $res) {
            if (array_key_exists('document', $res)) {
                array_push($results, array(
                    'document' => $res['document'],
                    'index' => $res['index'],
                    'relevance_score' => $res['relevance_score']
                ));
            } else {
                array_push($results, array(
                    'index' => $res['index'],
                    'relevance_score' => $res['relevance_score']
                ));
            }
        }
        return $results;
    }

    /**
     * Generates embeddings for a given array of texts.
     *
     * @param array $texts An array of texts for which embeddings are to be generated.
     * @param string|null $model The model to be used for generating the embeddings.
     * @param string|null $truncate The value to truncate the text.
     * @param bool|null $compress If true, the embeddings will be compressed.
     * @param string|null $compression_codebook The codebook to use for compression.
     *
     * @return array The embeddings, compressed embeddings and meta information.
     */
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
    
        for ($i = 0; $i < count($texts); $i += self::BATCH_SIZE) {
            $texts_batch = array_slice($texts, $i, $i + self::BATCH_SIZE);
            array_push($json_bodys, [
                "model" => $model,
                "texts" => $texts_batch,
                "truncate" => $truncate,
                "compress" => $compress,
                "compression_codebook" => $compression_codebook,
            ]);
        }
       $json_bodys = $this->cleanPayload($json_bodys);
    
        $meta = null;
        foreach ($json_bodys as $json_body) {
            $result = $this->request(Endpoints::EMBED, $json_body, "POST");
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

    /**
     * Classifies a given set of inputs based on examples.
     *
     * @param array $inputs The inputs to be classified.
     * @param array $examples The examples based on which classification is to be done.
     * @param string|null $model The model to be used for the classification.
     * @param string|null $preset The preset to use for the classification.
     * @param string|null $truncate The value to truncate the text.
     *
     * @return array The classifications and meta information.
     */
    public function classify(
        array $inputs = [],
        array $examples = [],
        ?string $model = "embed-english-v2.0",
        ?string $preset = null,
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

        $json_body = $this->cleanPayload($json_body);

        $response = $this->request(Endpoints::CLASSIFY, $json_body, "POST");
    
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

    /**
     * Generates a summary for the given text.
     *
     * @param string $text The text to be summarized.
     * @param string|null $model The model to be used for generating the summary.
     * @param string|null $length The length of the summary.
     * @param string|null $format The format of the summary.
     * @param float|null $temperature The temperature to use for generating the summary.
     * @param string|null $additional_command Any additional command to be used for generating the summary.
     * @param string|null $extractiveness The extractiveness to be used for generating the summary.
     *
     * @return array The summary, id, and meta information.
     */
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

        $json_body = $this->cleanPayload($json_body);

        $response = $this->request(Endpoints::SUMMARIZE, $json_body, "POST");

        $summarizeResponse = [
            "id" => $response["id"],
            "summary" => $response["summary"],
            "meta" => $response["meta"]
        ];

        return $summarizeResponse;
    }
    
}