<?php

namespace ibs;

/**
 * Uniform JSON responses for IBS Sync Connector (read-only).
 */
class api_response
{
    private $request;
    private $response;

    public function __construct($registry)
    {
        $this->request = $registry->get('request');
        $this->response = $registry->get('response');
    }

    public function send(array $payload, int $statusCode = 200): void
    {
        if (!isset($payload['read_only'])) {
            $payload['read_only'] = true;
        }

        if (!isset($payload['connector_version']) && defined('IBS_SYNC_CONNECTOR_VERSION')) {
            $payload['connector_version'] = IBS_SYNC_CONNECTOR_VERSION;
        }

        $protocol = $this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $this->response->addHeader($protocol . ' ' . $statusCode);
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        $this->response->setOutput(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function error(string $message, int $statusCode = 401): void
    {
        $this->send([
            'success' => false,
            'error' => $message,
            'read_only' => true,
        ], $statusCode);
    }
}
