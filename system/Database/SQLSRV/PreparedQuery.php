<?php

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Database\SQLSRV;

use BadMethodCallException;
use CodeIgniter\Database\BasePreparedQuery;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;

/**
 * Prepared query for Postgre
 *
 * @extends BasePreparedQuery<false|resource, resource>
 */
class PreparedQuery extends BasePreparedQuery
{
    /**
     * Parameters array used to store the dynamic variables.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The result boolean from a sqlsrv_execute.
     *
     * @var bool
     */
    protected $result;

    /**
     * A reference to the db connection to use.
     *
     * @var Connection
     */
    protected $db;

    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }

    /**
     * Prepares the query against the database, and saves the connection
     * info necessary to execute the query later.
     *
     * NOTE: This version is based on SQL code. Child classes should
     * override this method.
     *
     * @param array $options Options takes an associative array;
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function _prepare(string $sql, array $options = [])
    {
        // Prepare parameters for the query
        $queryString = $this->getQueryString();

        $parameters = $this->parameterize($queryString);

        // Prepare the query
        $this->statement = sqlsrv_prepare($this->db->connID, $sql, $parameters);

        if (! $this->statement) {
            if ($this->db->DBDebug) {
                throw new DatabaseException($this->db->getAllErrorMessages());
            }

            $info              = $this->db->error();
            $this->errorCode   = $info['code'];
            $this->errorString = $info['message'];
        }

        return $this;
    }

    /**
     * Takes a new set of data and runs it against the currently
     * prepared query. Upon success, will return a Results object.
     */
    public function _execute(array $data): bool
    {
        if (! isset($this->statement)) {
            throw new BadMethodCallException('You must call prepare before trying to execute a prepared statement.');
        }

        foreach ($data as $key => $value) {
            $this->parameters[$key] = $value;
        }

        $this->result = sqlsrv_execute($this->statement);

        if ($this->result === false && $this->db->DBDebug) {
            throw new DatabaseException($this->db->getAllErrorMessages());
        }

        return (bool) $this->result;
    }

    /**
     * Returns the result object for the prepared query.
     *
     * @return mixed
     */
    public function _getResult()
    {
        return $this->result;
    }

    /**
     * Deallocate prepared statements
     */
    protected function _close(): bool
    {
        return sqlsrv_free_stmt($this->statement);
    }

    /**
     * Handle parameters
     */
    protected function parameterize(string $queryString): array
    {
        $numberOfVariables = substr_count($queryString, '?');

        $params = [];

        for ($c = 0; $c < $numberOfVariables; $c++) {
            $this->parameters[$c] = null;
            $params[]             = &$this->parameters[$c];
        }

        return $params;
    }
}
