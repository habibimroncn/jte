<?php

namespace TextOperation\Client;

use TextOperation\TextOperation;

/**
 * Handles the client part of the OT synchronization protocol. Transforms
 * incoming operations from the server, buffers operations from the user and
 * sends them to the server at the right time.
 */
abstract class Client
{
    protected $revision;

    protected $state;

    /**
     * create new Client.
     *
     * @param mixed $revision
     */
    public function __construct($revision)
    {
        $this->revision = $revision;
        $this->state = new Synchronized();
    }

    /**
     * Call this method when the user (!) changes the document.
     */
    public function applyClient(TextOperation $operation)
    {
        $this->state = $this->state->applyClient($this, $operation);
    }

    /**
     * Call this method with a new operation from the server.
     */
    public function applyServer(TextOperation $operation)
    {
        $this->revision += 1;
        $this->state = $this->state->applyServer($this, $operation);
    }

    /**
     * Call this method when the server acknowledges an operation send by
     * the current user (via the send_operation method).
     */
    public function serverAck()
    {
        $this->revision += 1;
        $this->state = $this->state->serverAck($this);
    }

    /**
     * Get current revision.
     */
    public function getRevision()
    {
        return $this->revision;
    }
}
