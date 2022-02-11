<?php

declare(strict_types=1);

namespace VenityNetwork\CurlLib;

use Exception;

class CurlResponse{

    public function __construct(
        private int        $id,
        private ?int       $statusCode = null,
        private ?array     $headers = null,
        private ?string    $body = null,
        private ?Exception $exception = null) {

    }

    /**
     * @return int
     */
    public function getId(): int{
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getStatusCode(): ?int{
        return $this->statusCode;
    }

    /**
     * @return array|null
     */
    public function getHeaders(): ?array{
        return $this->headers;
    }

    /**
     * @return string|null
     */
    public function getBody(): ?string{
        return $this->body;
    }

    /**
     * @return Exception|null
     */
    public function getException(): ?Exception{
        return $this->exception;
    }
}