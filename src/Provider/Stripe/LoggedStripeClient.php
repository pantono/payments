<?php

namespace Pantono\Payments\Provider\Stripe;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Stripe\HttpClient\ClientInterface as StripeClientInterface;

class LoggedStripeClient implements StripeClientInterface
{
    private GuzzleClientInterface $client;

    public function __construct(GuzzleClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $options = [
            'headers' => $this->prepareHeaders($headers),
            'http_errors' => false,
        ];

        if (strtolower($method) === 'get') {
            $options['query'] = $params;
        } elseif ($params !== null) {
            $options['form_params'] = $params;
        }

        $response = $this->client->request($method, $absUrl, $options);

        return [
            (string)$response->getBody(),
            $response->getStatusCode(),
            $this->prepareResponseHeaders($response->getHeaders()),
        ];
    }

    private function prepareHeaders(array $headers): array
    {
        $preparedHeaders = [];
        foreach ($headers as $key => $value) {
            if (is_int($key) && is_string($value) && str_contains($value, ':')) {
                [$key, $value] = explode(':', $value, 2);
                $preparedHeaders[trim($key)] = trim($value);
                continue;
            }

            $preparedHeaders[$key] = $value;
        }

        return $preparedHeaders;
    }

    private function prepareResponseHeaders(array $headers): array
    {
        $preparedHeaders = [];
        foreach ($headers as $key => $value) {
            $preparedHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }

        return $preparedHeaders;
    }
}
