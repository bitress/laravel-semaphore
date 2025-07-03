<?php

namespace Bitress\LaravelSemaphore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\ResponseInterface;

/**
 * SemaphoreClient - A Laravel wrapper for the Semaphore SMS API
 * 
 * This client provides an interface to interact with Semaphore's v4 API
 * for sending SMS messages, managing accounts, and retrieving message data.
 * 
 * Features:
 * - Automatic caching for GET requests to reduce API calls
 * - Error handling with detailed exception information
 * - Support for all major Semaphore API endpoints
 */
class SemaphoreClient
{
    /** @var Client The HTTP client for making API requests */
    protected Client $http;
    
    /** @var string The API key for authenticating with Semaphore */
    protected string $apiKey;

    /** @var string Base URL for the Semaphore API v4 */
    protected const BASE_URI = 'https://semaphore.co/api/v4/';
    
    /** @var float Request timeout in seconds */
    protected const TIMEOUT = 5.0;
    
    /** @var int Cache TTL for GET requests in seconds */
    protected const CACHE_TTL = 30; // seconds

    /**
     * Initialize the Semaphore client
     * 
     * @param string $apiKey The API key for authentication
     * @param Client|null $httpClient Optional custom HTTP client (for testing)
     */
    public function __construct(string $apiKey, ?Client $httpClient = null)
    {
        $this->apiKey = $apiKey;

        // Use provided HTTP client or create a new one with default configuration
        $this->http = $httpClient ?? new Client([
            'base_uri' => self::BASE_URI,
            'timeout' => self::TIMEOUT,
        ]);
    }

    /**
     * Send an HTTP request to Semaphore API with automatic caching for GET requests
     * 
     * GET requests are cached to reduce API calls and improve performance.
     * POST/PUT requests are not cached as they typically modify data.
     * 
     * @param string $method HTTP method (GET, POST, PUT, etc.)
     * @param string $uri API endpoint URI
     * @param array $options Additional request options (query params, form data, etc.)
     * @return array Parsed response data or error information
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        if ($method === 'GET') {
            // Add API key to query parameters for GET requests
            $options['query']['apikey'] = $this->apiKey;

            // Check cache first to avoid unnecessary API calls
            $cacheKey = $this->makeCacheKey($uri, $options['query'] ?? []);
            return Cache::remember($cacheKey, self::CACHE_TTL, fn() => $this->performRequest($method, $uri, $options));
        }

        // For POST, PUT, etc., add API key to form parameters (no caching)
        $options['form_params']['apikey'] = $this->apiKey;
        return $this->performRequest($method, $uri, $options);
    }

    /**
     * Perform the actual HTTP request using Guzzle
     * 
     * This method handles the low-level HTTP communication and exception handling.
     * 
     * @param string $method HTTP method
     * @param string $uri API endpoint URI
     * @param array $options Request options
     * @return array Parsed response data or error information
     */
    protected function performRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $uri, $options);
            return $this->parseResponse($response);
        } catch (RequestException $e) {
            // Convert HTTP exceptions to structured error arrays
            return $this->handleException($e);
        }
    }

    /**
     * Generate a unique cache key for GET requests
     * 
     * The cache key is based on the URI and query parameters to ensure
     * different requests get different cache entries.
     * 
     * @param string $uri The API endpoint URI
     * @param array $query Query parameters
     * @return string MD5 hash of the request signature
     */
    protected function makeCacheKey(string $uri, array $query = []): string
    {
        // Sort query parameters to ensure consistent cache keys
        // regardless of parameter order
        ksort($query);
        $key = $uri . '?' . http_build_query($query);
        return 'semaphore:' . md5($key);
    }

    /**
     * Parse a successful HTTP response
     * 
     * @param ResponseInterface $response The HTTP response object
     * @return array Decoded JSON response data
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * Handle HTTP request exceptions and format error information
     * 
     * @param RequestException $e The caught request exception
     * @return array Structured error information
     */
    protected function handleException(RequestException $e): array
    {
        $error = ['error' => $e->getMessage()];

        // Include additional error details if available
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $error['status'] = $response->getStatusCode();
            $error['body'] = json_decode((string) $response->getBody(), true);
        }

        return $error;
    }

    /*
    |--------------------------------------------------------------------------
    | Messaging Methods
    |--------------------------------------------------------------------------
    |
    | These methods handle SMS sending and message management operations.
    |
    */

    /**
     * Send a standard SMS message
     * 
     * @param array $data Message data including 'number', 'message', etc.
     * @return array API response with message ID or error details
     */
    public function sendMessage(array $data): array
    {
        return $this->request('POST', 'messages', ['form_params' => $data]);
    }

    /**
     * Send a priority SMS message (faster delivery, higher cost)
     * 
     * @param array $data Message data including 'number', 'message', etc.
     * @return array API response with message ID or error details
     */
    public function sendPriority(array $data): array
    {
        return $this->request('POST', 'priority', ['form_params' => $data]);
    }

    /**
     * Send an OTP (One-Time Password) message
     * 
     * @param array $data OTP data including 'number', 'message', etc.
     * @return array API response with OTP details or error information
     */
    public function sendOtp(array $data): array
    {
        return $this->request('POST', 'otp', ['form_params' => $data]);
    }

    /**
     * Retrieve sent messages with optional filtering
     * 
     * @param array $filters Optional filters (limit, page, startDate, endDate, etc.)
     * @return array List of messages matching the criteria
     */
    public function getMessages(array $filters = []): array
    {
        return $this->request('GET', 'messages', ['query' => $filters]);
    }

    /**
     * Get details of a specific message by ID
     * 
     * @param string $id The message ID
     * @return array Message details or error information
     */
    public function getMessageById(string $id): array
    {
        return $this->request('GET', "messages/{$id}");
    }

    /*
    |--------------------------------------------------------------------------
    | Account Management Methods
    |--------------------------------------------------------------------------
    |
    | These methods provide access to account information, transactions,
    | sender names, and user management.
    |
    */

    /**
     * Get account information including balance and status
     * 
     * @return array Account details (balance, status, etc.)
     */
    public function getAccount(): array
    {
        return $this->request('GET', 'account');
    }

    /**
     * Retrieve account transaction history
     * 
     * @param array $filters Optional filters (limit, page, startDate, endDate, etc.)
     * @return array List of transactions
     */
    public function getTransactions(array $filters = []): array
    {
        return $this->request('GET', 'account/transactions', ['query' => $filters]);
    }

    /**
     * Get registered sender names for the account
     * 
     * @param array $filters Optional filters for sender name lookup
     * @return array List of approved sender names
     */
    public function getSenderNames(array $filters = []): array
    {
        return $this->request('GET', 'account/sendernames', ['query' => $filters]);
    }

    /**
     * Retrieve users associated with the account
     * 
     * @param array $filters Optional filters for user lookup
     * @return array List of account users
     */
    public function getUsers(array $filters = []): array
    {
        return $this->request('GET', 'account/users', ['query' => $filters]);
    }
}