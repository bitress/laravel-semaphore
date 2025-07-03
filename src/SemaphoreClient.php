<?php

namespace Bitress\LaravelSemaphore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * SemaphoreClient - A Laravel wrapper for the Semaphore SMS API
 */
class SemaphoreClient
{
    protected Client $http;
    protected string $apiKey;

    protected const BASE_URI = 'https://semaphore.co/api/v4/';
    protected const TIMEOUT = 5.0;

    public function __construct(string $apiKey, ?Client $httpClient = null)
    {
        $this->apiKey = $apiKey;

        $this->http = $httpClient ?? new Client([
            'base_uri' => self::BASE_URI,
            'timeout' => self::TIMEOUT,
        ]);
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        if ($method === 'GET') {
            $options['query']['apikey'] = $this->apiKey;
        } else {
            $options['form_params']['apikey'] = $this->apiKey;
        }

        return $this->performRequest($method, $uri, $options);
    }

    protected function performRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $uri, $options);
            return $this->parseResponse($response);
        } catch (RequestException $e) {
            return $this->handleException($e);
        }
    }

    protected function parseResponse(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    protected function handleException(RequestException $e): array
    {
        $error = ['error' => $e->getMessage()];

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $error['status'] = $response->getStatusCode();
            $error['body'] = json_decode((string) $response->getBody(), true);
        }

        return $error;
    }

    // Messaging Methods

    public function sendMessage(array $data): array
    {
        return $this->request('POST', 'messages', ['form_params' => $data]);
    }

    public function sendPriority(array $data): array
    {
        return $this->request('POST', 'priority', ['form_params' => $data]);
    }

    public function sendOtp(array $data): array
    {
        return $this->request('POST', 'otp', ['form_params' => $data]);
    }

    public function getMessages(array $filters = []): array
    {
        return $this->request('GET', 'messages', ['query' => $filters]);
    }

    public function getMessageById(string $id): array
    {
        return $this->request('GET', "messages/{$id}");
    }

    // Account Management Methods

    public function getAccount(): array
    {
        return $this->request('GET', 'account');
    }

    public function getTransactions(array $filters = []): array
    {
        return $this->request('GET', 'account/transactions', ['query' => $filters]);
    }

    public function getSenderNames(array $filters = []): array
    {
        return $this->request('GET', 'account/sendernames', ['query' => $filters]);
    }

    public function getUsers(array $filters = []): array
    {
        return $this->request('GET', 'account/users', ['query' => $filters]);
    }
}
