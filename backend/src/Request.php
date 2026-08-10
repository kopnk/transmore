<?php

namespace TransMore\Backend;

class Request
{
    private array $body;
    private array $query;
    private string $method;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->query = $_GET;
        $this->body = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
    }

    public function method(): string
    {
        return $this->method;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
}
