<?php

namespace TextOperation;

interface ServerWorker
{
    /**
     * Transforms an operation coming from a client against all concurrent
     * operation, applies it to the current document and returns the operation
     * to send to the clients.
     *
     * @param mixed $user_id
     * @param mixed $docid
     * @param mixed $revision
     * @param mixed $operation
     */
    public function receiveOperation($user_id, $docid, $revision, $operation);
}
