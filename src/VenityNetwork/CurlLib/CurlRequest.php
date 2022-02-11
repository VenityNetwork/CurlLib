<?php

declare(strict_types=1);

namespace VenityNetwork\CurlLib;

class CurlRequest{

    public function __construct(
        private int $id,
        private string $url,
        private array $headers = [],
        private bool $post = false,
        private string $postField = "",
        private array $curlOpts = [],
        private int $timeout = 10
    ) {
    }

    /**
     * @return int
     */
    public function getId(): int{
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUrl(): string{
        return $this->url;
    }

    /**
     * @return array
     */
    public function getHeaders(): array{
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getPostField(): string{
        return $this->postField;
    }

    /**
     * @return bool
     */
    public function isPost(): bool{
        return $this->post;
    }

    /**
     * @return array
     */
    public function getCurlOpts(): array{
        return $this->curlOpts;
    }

    /**
     * @return int
     */
    public function getTimeout(): int{
        return $this->timeout;
    }
}