<?php

namespace LBausch\CephRadosgwAdmin;

use Psr\Http\Message\ResponseInterface;

class ApiResponse
{
    /**
     * Response from API.
     */
    protected ResponseInterface $response;

    /**
     * Decoded response.
     */
    protected ?array $decoded;

    protected function __construct(ResponseInterface $response)
    {
        $this->response = $response;

        $body = $this->getResponse()->getBody();

        $body->rewind();
        $content = $body->getContents();
        $body->rewind();

        // Decode the API response
        $this->decoded = json_decode($content, $associative = true);
    }

    /**
     * Create from response.
     *
     * @throws ApiException
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $self = new self($response);

        $self->shouldThrowException();

        return $self;
    }

    /**
     * Throw exception if request failed.
     *
     * @throws ApiException
     */
    public function shouldThrowException(): void
    {
        if ($this->succeeded()) {
            return;
        }

        throw new ApiException($this->get('Code'), $this->getResponse()->getStatusCode());
    }

    /**
     * Get response.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Has response value?
     */
    public function has(string $name): bool
    {
        return isset($this->decoded[$name]);
    }

    /**
     * Get value from response.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $name = null, $default = null)
    {
        if (null === $name && null === $default) {
            return $this->decoded;
        }

        if (!$this->has($name)) {
            return $default;
        }

        return $this->decoded[$name];
    }

    /**
     * Detect whether the request has succeeded.
     */
    public function succeeded(): bool
    {
        $code = $this->getResponse()->getStatusCode();

        return $code >= 200 && $code < 300;
    }

    /**
     * Detect whether the request has failed.
     */
    public function failed(): bool
    {
        return !$this->succeeded();
    }
}
