<?php

namespace TextOperation\Client;

use TextOperation\TextOperation;

class AwaitingWithBuffer implements ClientState
{
    /**
     * @var \TextOperation\TextOperation
     */
    protected $outstanding;

    /**
     * @var \TextOperation\TextOperation
     */
    protected $buffer;

    /**
     * In the 'awaitingWithBuffer' state, the client is waiting for an operation
     * to be acknowledged by the server while buffering the edits the user makes.
     */
    public function __construct(TextOperation $outstanding, TextOperation $buffer)
    {
        $this->outstanding = $outstanding;
        $this->buffer = $buffer;
    }

    /**
     * @see TextOperation\Client\ClientState
     */
    public function applyClient(Client $client, TextOperation $operation)
    {
        $newbuffer = $this->buffer->compose($operation);

        return new self($this->outstanding, $newbuffer);
    }

    /**
     * @see TextOperation\Client\ClientState
     */
    public function applyServer(Client $client, TextOperation $operation)
    {
        list($outstanding_p, $operation_p) = TextOperation::transform($this->outstanding, $operation);
        list($buffer_p, $operation_pp) = TextOperation::transform($this->buffer, $operation_p);
        $client->applyOperation($operation_pp);

        return new self($outstanding_p, $buffer_p);
    }

    public function serverAck(Client $client)
    {
        $client->sendOperation($client->getRevision(), $this->buffer);

        return new AwaitingConfirm($this->buffer);
    }
}
