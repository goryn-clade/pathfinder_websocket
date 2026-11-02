<?php

namespace Exodus4D\Socket\Http;


use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\CloseResponseTrait;
use Ratchet\Http\OriginCheck as RatchetOriginCheck;

/**
 * Ratchet's OriginCheck reads getHeader('Origin')[0], which warns "Undefined array key 0"
 * when a client sends no Origin header. An empty Origin is never allowed, so close with 403 here.
 */
class OriginCheck extends RatchetOriginCheck {
    use CloseResponseTrait;


    /**
     * @param ConnectionInterface $conn
     * @param RequestInterface|null $request
     * @return mixed
     */
    public function onOpen(ConnectionInterface $conn, ?RequestInterface $request = null) {
        if($request === null || $request->getHeaderLine('Origin') === ''){
            return $this->close($conn, 403);
        }

        return parent::onOpen($conn, $request);
    }
}
