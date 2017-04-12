<?php
namespace TextOperation;

class DefaultServerWorker implements ServerWorker
{
	protected $document;

	protected $backend;

	/**
	 *
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