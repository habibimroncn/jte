<?php

namespace TextOperation\Client;

use TextOperation\TextOperation;

interface ClientState
{
    /**
     * When user make an edit, do your intention here. Normally it depend on state.
     *
     * @param TextOperation\Client\Client $client
     * @param TextOperation\TextOperation $operation
     *
     * @return TextOperation\Client\ClientState The current state after this operation called
     */
    public function applyClient(Client $client, TextOperation $operation);

    /**
     * We are receiving an operation from server.
     *
     * @param TextOperation\Client\Client $client
     * @param TextOperation\TextOperation $operation
     *
     * @return TextOperation\Client\ClientState The current state after this operation called
     */
    public function applyServer(Client $client, TextOperation $operation);

    /**
     * The pending operation has been acknowledged.
     *
     * @param TextOperation\Client\Client $client
     *
     * @return TextOperation\Client\ClientState The current state after this operation called
     */
    public function serverAck(Client $client);
}
