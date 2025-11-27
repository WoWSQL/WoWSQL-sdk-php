<?php

namespace WOWSQL;

/**
 * Base exception for WOWSQL SDK errors.
 */
class WOWSQLException extends \Exception
{
    protected $statusCode;
    protected $response;

    public function __construct($message, $statusCode = null, $response = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getResponse()
    {
        return $this->response;
    }
}

/**
 * Storage-specific exception.
 */
class StorageException extends WOWSQLException
{
}

/**
 * Exception raised when storage limit would be exceeded.
 */
class StorageLimitExceededException extends StorageException
{
    public function __construct($message, $response = null)
    {
        parent::__construct($message, 413, $response);
    }
}

/**
 * Exception raised when operation requires service role key but anonymous key was used.
 */
class PermissionException extends WOWSQLException
{
    public function __construct($message)
    {
        parent::__construct($message, 403);
    }
}

