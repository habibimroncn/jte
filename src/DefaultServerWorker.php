<?php

namespace TextOperation;

class DefaultServerWorker implements ServerWorker
{
    /**
     * @var string
     */
    protected $document;

    /**
     * @var TextOperation\ServerBackend
     */
    protected $backend;

    /**
     * constructor. Create DefaultServerWorker with initial document and ServerBackend
     * instance (used when save the operation).
     *
     * @var string
     * @var TextOperation\ServerBackend $backend
     *
     * @param mixed $document
     */
    public function __construct($document, ServerBackend $backend)
    {
        $this->document = $document;
        $this->backend = $backend;
    }

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
    public function receiveOperation($user_id, $docid, $revision, $operation)
    {
        $lastByUser = $this->backend->getLastRevisionfromUser($user_id);
        if ($lastByUser && $lastByUser >= $revision) {
            return;
        }
        $concurrent_operations = $this->backend->getOperations($revision);
        foreach ($concurrent_operations as $concurrent_operation) {
            list($operation, $_s) = TextOperation::transform($operation, $concurrent_operation);
        }
        $this->document = $operation->apply($this->document);
        $this->backend->save($docid, $this->document);
        $this->backend->saveOperation($user_id, $id, $operation);

        return $operation;
    }
}
