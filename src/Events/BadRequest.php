<?php

namespace Sicet7\Server\Events;

final readonly class BadRequest
{
    public function __construct(
        public \Throwable $throwable
    ) {
    }
}