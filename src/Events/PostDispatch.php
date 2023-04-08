<?php

namespace Sicet7\Server\Events;

use Psr\Http\Message\ResponseInterface;

final readonly class PostDispatch
{
    public function __construct(
        public ResponseInterface $response,
    ) {
    }
}