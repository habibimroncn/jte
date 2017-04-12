<?php
namespace TextOperation;

/**
 * Used to log/persist operations
 */
interface ServerBackend
{
    /**
     * Save document
     */
    public function save($id, $doc);

    /**
     * Get document by Id
     */
    public function get($id);

    /**
     *
     */
    public function saveOperation($user_id, $id, $operation);

    /**
     *
     */
    public function getOperations($start, $end = null);

    /**
     *
     */
    public function getLastRevisionfromUser($user_id);
}