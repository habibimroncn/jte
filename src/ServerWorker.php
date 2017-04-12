<?php
namespace TextOperation;

interface ServerWorker
{
	/**
	 *
	 */
	public function receiveOperation($user_id, $docid, $revision, $operation);
}