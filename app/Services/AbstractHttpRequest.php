<?php

namespace App\Services;

use \Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ConnectException;

abstract class AbstractHttpRequest
{
    /**
     * @var Http $client
     */
    protected $client;

    /**
     * @var string $baseUrl
     */
    protected $baseUrl;


    /**
     * @param string $uri
     * @param array $query
     * @param array $body
     * @param null $requestMethod
     * @return $this
     */
    public function beforeClient($uri = '', $query = [], $body = [], $requestMethod = null): AbstractHttpRequest
    {
        return $this;
    }

    /**
     * @param $uri
     * @param array $query
     * @return array
     */
    public function get($uri, $query = [])
    {
        return $this->beforeClient($uri, $query, [], 'GET')->request('get', $uri, $query, []);
    }

    /**
     * @param $uri
     * @param array $query
     * @param array $body
     * @return array
     */
    public function delete($uri, array $query = [], array $body = [])
    {
        return $this->beforeClient($uri, $query, $body, 'DELETE')->request('delete', $uri, $query, $body);
    }

    /**
     * @param $uri
     * @param array $body
     * @return array
     */
    public function post($uri, $body = [])
    {
        return $this->beforeClient($uri, [], $body, 'POST')->request('post', $uri, [], $body);
    }

    /**
     * @param $uri
     * @param array $query
     * @param array $body
     * @return array
     */
    public function postWithQuery($uri, $query = [], $body = [])
    {
        return $this->beforeClient()->request('post', $uri, $query, $body);
    }

    /**
     * @param $params
     * @param $allowedParam
     * @return array
     */
    protected function filterParameters(array $params, $allowedParam)
    {
        $parameters = [];
        foreach ($params as $key => $param) {
            if (in_array($key, $allowedParam)) {
                $parameters[$key] = $param;
            }
        }
        return $parameters;
    }

    /**
     * @return array
     */
    protected function defaultQuery()
    {
        return [];
    }

    /**
     * @param Response $response
     * @return array|string
     */
    public function response(Response $response)
    {
        $contentType = $response->header('Content-Type');
        if (Str::contains($contentType, "application/xml")) {
            return $response->body();
        } elseif (Str::contains($contentType, "text/csv")) {
            return $response->body();
        }
        return $response->json();
    }

    protected function postCheckHeaders($headers, $status): void
    {
        //
    }


    protected $logger;


    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function log()
    {
        return $this->logger ?? Log::channel('daily');
    }

    /**
     * @param $method
     * @param $url
     * @param array $query
     * @param array $body
     * @return array
     */
    public function request($method, $url, $query = [], $body = [])
    {
        try {
            $this->log()->debug(json_encode(
                    [
                        'method' => $method,
                        'url' => $url,
                        'body' => $body,
                        'query' => $query
                    ], JSON_PRETTY_PRINT
                )
            );
            foreach ($this->defaultQuery() as $key => $value) {
                $query[$key] = $value;
            }

            switch ($method) {
                case 'get':
                    $response = $this->client->get($url, $query);
                    break;
                case 'post':
                    if (!empty($query)) {
                        $url = $url . '?' . http_build_query($query);
                    }
                    $response = $this->client->post($url, $body);
                    break;
                case 'delete':
                    if (!empty($query)) {
                        $url = $url . '?' . http_build_query($query);
                    }
                    $response = $this->client->delete($url, $body);
                    break;
                case 'put':
                    $response = $this->client->put($url, $body);
                    break;
                default:
                    throw new Exception($method . ' is not found');
            }

            $this->postCheckHeaders($response->headers(), $response->status());
            $data = $this->response($response);

            if ($response->successful()) {
//                Log::debug(json_encode($data, JSON_PRETTY_PRINT));

                return [
                    'status' => 'success',
                    'status_code' => $response->status(),
                    'data' => $data
                ];
            }

            if ($response->clientError()) {
                $errorType = 'client_error';
            } elseif ($response->serverError()) {
                $errorType = 'server_error';
            } else {
                $errorType = 'unknown_error';
            }

            $responseBody = [
                'status' => 'error',
                'error_type' => $errorType,
                'status_code' => $response->status(),
                'error' => $data,
                'request' => [
                    'base_url' => $this->baseUrl,
                    'method' => $method,
                    'uri' => $url,
                    'query' => $query,
                    'json' => $body
                ]
            ];
            if(isset($data['error']) && $data['error'] !== 'Account does not have enough margin for order.'){
                $this->log()->error(json_encode($responseBody));
            }
            return $responseBody;
        } catch (ConnectException $exception) {
            $responseBody = [
                'status' => 'error',
                'status_code' => $exception->getCode(),
                'error_type' => 'connection_error',
                'request' => [
                    'base_url' => $this->baseUrl,
                    'method' => $method,
                    'uri' => $url,
                    'query' => $query,
                    'json' => $body
                ],
                'error' => $exception->getMessage()
            ];
            $this->log()->error(json_encode($responseBody));
            return $responseBody;
        } catch (Exception $e) {
            $responseBody = [
                'status' => 'error',
                'status_code' => $e->getCode(),
                'error_type' => 'system_error',
                'request' => [
                    'base_url' => $this->baseUrl,
                    'method' => $method,
                    'uri' => $url,
                    'query' => $query,
                    'json' => $body
                ],
                'error' => $e->getMessage()
            ];
            $this->log()->error(json_encode($responseBody));
            return $responseBody;
        }
    }
}
