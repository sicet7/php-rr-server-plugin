<?php

namespace Sicet7\Server\Events;

use Psr\Http\Message\ServerRequestInterface;

final readonly class PreDispatch
{
    public function __construct(
        public ServerRequestInterface $response,
    ) {
    }
}