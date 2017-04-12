<?php

namespace TextOperation\Client;

use TextOperation\TextOperation;

class AwaitingConfirm implements ClientState
{
    /**
     * @var TextOperation\TextOperation
     */
    protected $outstanding;

    /**
     * Create new AwaitingConfirm with operation that still not acknowledged by
     * server.
     *
     * @param TextOperation\TextOperation
     */
    public function __construct(TextOperation $outstanding)
    {
        $this->outstanding = $outstanding;
    }

    /**
     * @see TextOperation\Client\ClientState
     */
    public function applyClient(Client $client, TextOperation $operation)
    {
        return new AwaitingWithBuffer($this->outstanding, $operation);
    }

    /**
     * @see TextOperation\Client\ClientState
     */
    public function applyServer(Client $client, TextOperation $operation)
    {
        list($outstanding_p, $operation_p) = TextOperation::transform($this->outstanding, $operation);
        $client->applyOperation($operation_p);

        return new self($outstanding_p);
    }

    /**
     * @see TextOperation\Client\ClientState
     */
    public function serverAck(Client $client)
    {
        return new Synchronized();
    }
}
