<?php
/**
 * @package cl_database Class
 * @copyright 2017 Chris Carlevato (https://github.com/chrislarrycarl)
 * @license http://www.gnu.org/licenses/lgpl-2.1.html
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 */

namespace ChristopherL;

use \PDO;

class Database
{
    const VERSION = 0.1;

    protected $config = [];

    protected $query_params = [];

    protected $query_history = [];

    protected $functions = array(
        '[count]'   => 'COUNT',
        '[sum]'     => 'SUM',
        '[avg]'     => 'AVG',
        '[min]'     => 'MIN',
        '[max]'     => 'MAX',
        '[group]'   => 'GROUP_CONCAT'
    );

    protected $operators = array(
        '[>]'       => ' %s > %s ',
        '[>=]'      => ' %s >= %s ',
        '[<]'       => ' %s < %s ',
        '[<=]'      => ' %s <= %s ',
        '[=]'       => ' %s = %s ',
        '[!=]'      => ' %s != %s ',
        '[~]'       => ' %s LIKE %s ',
        '[!~]'      => ' %s NOT LIKE %s ',
        '[null]'    => ' %s IS NULL ',
        '[!null]'   => ' %s IS NOT NULL ',
        '[is]'      => ' %s IS %s ',
        '[!is]'     => ' %s IS NOT %s ',
        '[<>]'      => ' %s BETWEEN %s AND %s ',
        '[!<>]'     => ' %s NOT BETWEEN %s AND %s ',
        '[in]'      => ' %s IN (%s) ',
        '[!in]'     => ' %s NOT IN (%s) '
    );

    protected $joins = array(
        '[>]'       => 'LEFT',
        '[<]'       => 'RIGHT',
        '[<>]'      => 'FULL OUTER',
        '[><]'      => 'INNER'
    );

    /**
     * Constructor
     *
     * @param array $config Session Configuration, supported settings include:
     * - server:    Database server type
     * - host:      Server hostname or IP
     * - username:  Database username
     * - password:  Database password
     * - database:  Database name
     * - port:      Datatbase server port number
     * - prefix:    Table prefix
     * - options:   Array of PDO options, EX):
     *              array(
     *                  PDO::ATTR_PERSISTENT => true
     *              );
     */
    public function __construct($config = []) {
        $this->config = [
            'server'    => isset($config['server'])     ? $config['server']     : 'mysql',
            'host'      => isset($config['host'])       ? $config['host']       : 'localhost',
            'username'  => isset($config['username'])   ? $config['username']   : '',
            'password'  => isset($config['password'])   ? $config['password']   : '',
            'database'  => isset($config['database'])   ? $config['database']   : 'test',
            'port'      => isset($config['port'])       ? $config['port']       : '3306',
            'prefix'    => isset($config['prefix'])     ? $config['prefix']     : '',
            'options'   => isset($config['options'])    ? $config['options']    : [],
        ];

        switch($config['server']) {
            /**
             * Microsoft SQL Server
             *
             * Windows:
             * @link https://secure.php.net/manual/en/ref.pdo-sqlsrv.php
             *
             * Everybody Else:
             * @link https://secure.php.net/manual/en/ref.pdo-dblib.php
             */
            case 'sqlserver':
                $this->config['driver'] = 'sqlsrv';
                $this->config['status'] = [
                    'driver_name' => 'DRIVER_NAME',
                    'error_mode' => 'ERRMODE',
                ];
                break;
            case 'dblib':
                $this->config['driver'] = 'dblib';
                $this->config['status'] = [
                    'driver_name' => 'DRIVER_NAME',
                    'error_mode' => 'ERRMODE',
                ];
                break;

            /**
             * Postgress
             * @link https://secure.php.net/manual/en/ref.pdo-pgsql.php
             */
            case 'pgsql':
                $this->config['driver'] = 'pgsql';
                $this->config['status'] = [
                    'driver_name' => 'DRIVER_NAME',
                    'error_mode' => 'ERRMODE',
                    'server_info' => 'SERVER_INFO',
                    'server_version' => 'SERVER_VERSION',
                    'client_version' => 'CLIENT_VERSION',
                    'connection_status' => 'CONNECTION_STATUS',
                ];
                break;

            /**
             * MySQL & MariaDB
             * @link https://secure.php.net/manual/en/ref.pdo-mysql.php
             */
            case 'mariadb':
            case 'mysql':
                $this->config['driver'] = 'mysql';
                $this->config['status'] = [
                    'driver_name' => 'DRIVER_NAME',
                    'error_mode' => 'ERRMODE',
                    'server_info' => 'SERVER_INFO',
                    'server_version' => 'SERVER_VERSION',
                    'client_version' => 'CLIENT_VERSION',
                    'connection_status' => 'CONNECTION_STATUS',
                ];
                break;

            /**
             * No default configured
             */
            default:
                $this->config['driver'] = '';
                break;
        }

        if (!in_array($this->config['driver'], PDO::getAvailableDrivers())) {
            $this->error('Server does not support driver: ' . $this->config['driver']);
        }

        try {
            $this->pdo = new \PDO(
                sprintf('%s:host=%s;port=%s;dbname=%s',
                    $this->config['driver'],
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['database']
                ),
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        }
        catch (PDOException $error) {
            $this->error($error->getMessage());
        }
    }

    /**
     * Prepare table name for use in query, prepend table prefix, optionally append column name if provided
     *
     * @param string $table                 Table name
     * @param string $column                Column name
     *
     * @return string                       Completed table name for use in query
     */
    private function prepare_table($table, $column = null) {
        return $this->config['prefix'] . $table . (isset($column) ? '.' . $column : '');
    }

    /**
     * Prepare column names for use in query, prepend table name
     *
     * @param array $columns
     * @return string
     */
    private function prepare_columns($columns) {
        $query_columns = array();

        foreach ($columns as $table => $column_names) {

            foreach($column_names as &$column_name) {

                $column_name = $this->config['prefix'] . $table . '.' . $column_name;

                $column_name = preg_replace_callback(
                    '/(.*?)([\[\{].*?[\]\}])(.*)/i',
                    function ($matches) use ($column_name) {
                        $column_name = $matches[1];
                        $function = (array_key_exists($matches[2], $this->functions)) ? $this->functions[$matches[2]] : '';

                        if ($function != '') {
                            $column_name = $function . '(' . $matches[1] . ')';
                        }

                        if ($function == '' && isset($matches[2])) {
                            $column_name .= ' AS ' . preg_replace(array('/\{/', '/\}/'), '', $matches[2]);
                        }
                        else if (isset($matches[3]) && $matches[3] != '') {
                            $column_name .= ' AS ' . preg_replace(array('/\{/', '/\}/'), '', $matches[3]);
                        }

                        return $column_name;
                    },
                    $column_name
                );

            }

            $query_columns = array_merge($query_columns, $column_names);
        }

        return implode(', ', $query_columns);
    }

    /**
     * Prepare where statement and parameter values for use in query
     *
     * @param array $where                  Where conditions for the statement
     * @param string $conjunction           Outer conjunction (AND/OR) used for nested where conditions
     *
     * @return array(string, params)        Array containing where condition string and array of query parameter values
     */
    private function prepare_where($where, $conjunction = null) {
        $query_where = '';
        $nested_where = $query_params = array();

        if (isset($where['AND']) || isset($where['OR']) || isset($where['SINGLE'])) {
            $query_and_or = array();
            $where_and_or = $where[key($where)];
            $outer_conjunction = key($where);

            foreach ($where_and_or as $table => $condition) {
                if (preg_match('/(AND|OR|SINGLE)/i', $table, $matches)) {
                    $table = trim(substr($table, 0, 3));
                }

                if (in_array($table, ['AND', 'OR'])) {
                    list($extra_where, $extra_params) = $this->prepare_where(array($table => $condition), $table);
                    $nested_where[] = $extra_where;
                    $query_params = array_merge($query_params, $extra_params);
                }

                foreach ($condition as $column => $value) {
                    preg_replace_callback(
                        '/(.*)(\[.*?\])/i',
                        function ($matches) use ($table, $value, &$query_params, &$query_and_or) {
                            $full_column = $this->prepare_table($table, $matches[1]);
                            $salt = mt_rand(0, 1000);

                            if (strpos($this->operators[$matches[2]], '(%s)') > 0) {
                                array_fill_keys($value, 'foo');

                                array_map(function ($key, $entry) use(&$value) {
                                    unset($value[$key]);
                                    return $value[':'.md5($key)] = $entry;
                                }, array_keys($value), $value);

                                $query_and_or[] = sprintf(
                                    $this->operators[$matches[2]],
                                    $full_column,
                                    implode(', ', array_keys($value))
                                );
                                $query_params = array_merge($query_params, $value);
                            }
                            else if (is_array($value) && $matches[2] != '[in]') {
                                $query_and_or[] = sprintf(
                                    $this->operators[$matches[2]],
                                    $full_column,
                                    ':'.md5($full_column.$salt),
                                    ':'.md5($full_column.$salt.'2')
                                );
                                $query_params[':'.md5($full_column.$salt)] = $value[0];
                                $query_params[':'.md5($full_column.$salt.'2')] = $value[1];
                            }
                            else {
                                $formated_operator = (strtotime($value) && !$this->has_support('STRINGDATE')) ? 'Convert(datetime, :' . md5($full_column.$salt) . ' )' : ':'.md5($full_column.$salt);

                                $query_and_or[] = sprintf(
                                    $this->operators[$matches[2]],
                                    $full_column,
                                    $formated_operator
                                );
                                if (substr_count($this->operators[$matches[2]], '%s') > 1) {
                                    $query_params[':'.md5($full_column.$salt)] = $value;
                                }
                            }
                        },
                        $column
                    );
                }
            }

            if (count($query_and_or) > 0) {
                if (isset($conjunction)) {
                    $query_where .= '( ' . implode(' ' . $conjunction . ' ', $query_and_or) . ' )';
                }
                else {
                    $query_where .= implode(' ' . $outer_conjunction . ' ', $query_and_or);
                }
            }
            else {
                $query_where .= implode(' ' . $outer_conjunction . ' ', $nested_where);
            }
        }

        if (isset($where['MATCH'])) {
            foreach ($where['MATCH']['columns'] as $table => $columns) {
                $columns = array_map(
                    function($column) use($table) {
                        return $this->prepare_table($table, $column);
                    },
                    $columns
                );
            }

            $match_param = ':'.md5($where['MATCH']['search']);
            $query_where .= (($query_where != '') ? ' AND ' : '') . ' MATCH(' .  implode(', ', $columns) . ') AGAINST(' . $match_param . ' ' . $where['MATCH']['option'] . ')';
            $query_params[$match_param] = $where['MATCH']['search'];
        }

        if (isset($where['GROUP'])) {
            $group_columns = array();

            foreach ($where['GROUP'] as $table => $columns) {
                $columns = array_map(
                    function($column) use($table) {
                        return $this->prepare_table($table, $column);
                    },
                    $columns
                );

                $group_columns = array_merge($group_columns, $columns);
            }

            $query_where .= ' GROUP BY ' . implode(', ', $group_columns);
        }

        if (isset($where['HAVING'])) {

            foreach ($where['HAVING'] as $table => $column) {
                if (count($where['HAVING'][$table]) != 3) {
                    $this->error('Incorrect number of parameters for HAVING clause: ' . print_r($where['HAVING'], true));
                }

                $having_param = ':'.md5($column[0]);

                $query_where .= ' HAVING ' . sprintf(
                        $this->operators[$column[1]],
                        $this->prepare_columns([$table => [$column[0]]]),
                        $having_param
                    );

                if (substr_count($this->operators[$column[1]], '%s') > 1) {
                    $query_params[$having_param] = $column[2];
                }
            }
        }

        if (isset($where['ORDER'])) {
            $query_where .= ' ORDER BY ';

            foreach ($where['ORDER'] as $table => $columns) {
                $columns = array_map(
                    function($column) use($table) {
                        preg_match('/(.*)(\[.*?\])/i', $column, $matches);

                        if (count($matches) > 0) {
                            $column = $this->prepare_table($table, str_replace($matches[2], '', $column)) . preg_replace('/\[|\]/i', ' ', $matches[2]);
                        }
                        else {
                            $column = $this->prepare_table($table, $column);
                        }

                        return $column;
                    },
                    $columns
                );

                $query_where .= implode(', ', $columns);
            }
        }

        if (isset($where['LIMIT'])) {
            $query_limit = '';

            if (is_int($where['LIMIT'])) {
                $query_limit .= 'LIMIT :limit';
                $query_params[':limit'] = (int) $where['LIMIT'];
            }
            else if (is_array($where['LIMIT']) && is_int($where['LIMIT'][0]) && is_int($where['LIMIT'][1])) {
                $query_limit .= 'LIMIT :limit :offset';
                $query_params[':limit'] = (int) $where['LIMIT'][0];

                if ($this->has_support('LIMIT')) {
                    $query_params[':offset'] = (int) $where['LIMIT'][1];
                }
            }

            $query_where .= ($this->has_support('LIMIT')) ? $query_limit : '';
        }

        return array($query_where, $query_params);
    }

    /**
     * Prepare join conditions for use in query
     *
     * @param string $table                 Source table name
     * @param array $join                   Join criteria, including join table and columns
     *
     * @return string                       Completed join condition for use in query
     */
    private function prepare_join($table, $join = []) {
        $query_join = '';

        if (count($join) > 0) {

            foreach ($join as $type => $columns) {

                $type = preg_replace_callback(
                    '/(\[.*?\])(.*)/i',
                    function ($matches) use ($type, $columns, $table) {
                        return sprintf('%s JOIN %s ON (%s = %s)',
                            $this->joins[$matches[1]],
                            $this->prepare_table($matches[2]),
                            $this->prepare_table($table, $columns[0]),
                            $this->prepare_table(str_replace($matches[0], $matches[2], $type), $columns[1])
                        );
                    },
                    $type
                );

                $query_join .= $type;
            }

        }

        return $query_join;
    }

    /**
     * Select data from the database using provided criteria
     *
     * @param string $table                 Name of the table to select from
     * @param array $columns                Column names to include in the result set
     * @param array $where                  Where clause conditions
     * @param array $join                   Join conditions
     *
     * @return array                        Query result set
     */
    public function select($table, $columns = ['*'], $where = [], $join = []) {

        if (!isset($table) || !is_array($columns) || !is_array($where) || !is_array($join)) {
            error('Invalid select request, table name required. Columns, where, and join must be array');
        }

        list($query_where, $query_params) = $this->prepare_where($where);

        if ($query_where != '' && preg_match('/^(MATCH|GROUP|HAVING|ORDER|LIMIT)/i', trim($query_where)) == 0) {
            $query_where = ' WHERE ' . $query_where;
        }

        $query = sprintf(
            "SELECT %s %s FROM %s %s %s;",
            ($this->has_support('TOP') && isset($query_params[':limit']) ? ' TOP :limit ' : ''),
            $this->prepare_columns($columns),
            $this->prepare_table($table),
            $this->prepare_join($table, $join),
            $query_where
        );

        $statement_handle = $this->pdo->prepare($query);

        foreach ($query_params as $parameter => $value) {
            switch (gettype($value)) {
                case "boolean":
                    $statement_handle->bindValue($parameter, $value, PDO::PARAM_BOOL);
                    break;
                case "integer":
                    $statement_handle->bindValue($parameter, $value, PDO::PARAM_INT);
                    break;
                case "NULL":
                    $statement_handle->bindValue($parameter, $value, PDO::PARAM_NULL);
                    break;
                case "string":
                default:
                    $statement_handle->bindValue($parameter, $value, PDO::PARAM_STR);
                    break;
            }
        }

        $statement_handle->execute();

        $error = $statement_handle->errorInfo();
        if (isset($error[2]) && $error[2] != '') {
            $this->error('cl_database class, PDO select statement resulted in error: ' . print_r($error, true));
        }

        $this->log_query($query, $query_params);

        return $statement_handle->fetchAll();
    }

    /**
     * Insert data into database using provided criteria
     *
     * @param string $table                 Name of the table to select from
     * @param array $columns                Column names with corresponding values to insert
     *
     * @return int                          Number of inserted records
     */
    public function insert($table, $columns = []) {
        $query_columns = $query_values = $query_params = array();
        $inserted_row_count = 0;

        if (!isset($table) || !count($columns) > 0) {
            $this->error('Invalid insert syntax, missing table or column values');
        }

        foreach ($columns as $insert) {
            $query_columns = array_keys($insert);

            array_map(
                function ($key, $value) use(&$insert) {
                    unset($insert[$key]);
                    return $insert[':'.md5($key)] = $value;
                }, array_keys($insert), $insert
            );

            $query_params[] = $insert;
            $query_values = array_keys($insert);
        }

        $query = sprintf(
            "INSERT INTO %s(%s) VALUES(%s);",
            $this->prepare_table($table),
            implode(', ', $query_columns),
            implode(', ', $query_values)
        );

        $statement_handle = $this->pdo->prepare($query);

        foreach ($query_params as $insert_params) {
            $statement_handle->execute($insert_params);

            $error = $statement_handle->errorInfo();
            if (isset($error[2]) && $error[2] != '') {
                $this->error('cl_database class, PDO insert statement resulted in error: ' . print_r($error, true));
            }

            $this->log_query($query, $insert_params);

            $inserted_row_count += $statement_handle->rowCount();
        }

        return $inserted_row_count;
    }

    /**
     * Update data in the database using provided criteria
     *
     * @param string $table                 Name of the table to select from
     * @param array $columns                Names of columns and values to update them to
     * @param array $where                  Where clause conditions
     *
     * @return int                          Number of updated records
     */
    public function update($table, $columns, $where) {
        $column_params = $query_params = array();

        array_map(
            function ($key, $value) use($table, &$columns, &$column_params) {
                $column_params[':'.md5($key)] = $value;
                return $columns[$key] = $key . ' = :'.md5($key);
            }, array_keys($columns), $columns
        );

        list($query_where, $query_params) = $this->prepare_where($where);
        $query_params = array_merge($query_params, $column_params);

        $query = sprintf(
            "UPDATE %s SET %s WHERE %s;",
            $this->prepare_table($table),
            implode(', ', $columns),
            $query_where
        );

        $statement_handle = $this->pdo->prepare($query);
        $statement_handle->execute($query_params);

        $error = $statement_handle->errorInfo();
        if (isset($error[2]) && $error[2] != '') {
            $this->error('cl_database class, PDO update statement resulted in error: ' . print_r($error, true));
        }

        $this->log_query($query, $query_params);

        return $statement_handle->rowCount();
    }

    /**
     * Delete data from the database using provided criteria
     *
     * @param string $table                 Name of the table to select from
     * @param array $where                  Where clause conditions
     * @param array $join                   Join conditions
     *
     * @return int                          Number of deleted records
     */
    public function delete($table, $where) {
        list($query_where, $query_params) = $this->prepare_where($where);

        $query = sprintf(
            "DELETE FROM %s WHERE %s;",
            $this->prepare_table($table),
            $query_where
        );

        $statement_handle = $this->pdo->prepare($query);
        $statement_handle->execute($query_params);

        $error = $statement_handle->errorInfo();
        if (isset($error[2]) && $error[2] != '') {
            $this->error('cl_database class, PDO select statement resulted in error: ' . print_r($error, true));
        }

        $this->log_query($query, $query_params);

        return $statement_handle->rowCount();
    }

    /**
     * Wrapper to directly execute a statement using the provided parameters
     *
     * @param string $query                     SQL statement to execute, optionally with named parameters
     * @param array $query_params               Array of named input parameters
     *
     * @return array (bool, int, array)         Array: successful, number of affected/retrieved rows, results set
     */
    public function execute($query, $query_params = array()) {
        $statement_handle = $this->pdo->prepare($query);
        $result = $statement_handle->execute($query_params);

        $error = $statement_handle->errorInfo();
        if (isset($error[2]) && $error[2] != '') {
            $this->error('cl_database class, PDO select statement resulted in error: ' . print_r($error, true));
        }

        $this->log_query($query, $query_params);

        return array(
            'successful' => $result,
            'affected_rows' => $statement_handle->rowCount(),
            'data' => $statement_handle->fetchAll()
        );
    }

    /**
     * Add query, and any errors that occured, to the query log
     *
     * @param string $query                 Prepared SQL query
     * @param array $query_params           Query parameters array
     */
    private function log_query($query, $query_params) {
        $log_parameters = array_keys($query_params);
        $log_values = array_map(
            function($value){
                return ($value == '' || !is_int($value)) ? "'$value'" : $value;
            }, array_values($query_params)
        );
        $this->query_history[] = str_ireplace($log_parameters, $log_values, $query);
    }

    /**
     * Get the log of queries run
     *
     * @return array                        Query history of executed statements
     */
    public function log() {
        return $this->query_history;
    }

    /**
     * Retrieve database connection details
     *
     * @return array                        Current connection details
     */
    public function info() {
        return array_map(
            function($value) {
                return (isset($value)) ? $this->pdo->getAttribute(constant('PDO::ATTR_' . $value)) : 'Unavailable';
            },
            $this->config['status']
        );
    }

    /**
     * Confirm if current server supports a given feature
     *
     * @param string $feature               Named feature
     *
     * @return bool                         Feature support status for current server
     */
    private function has_support($feature) {
        $support = false;

        switch ($feature) {
            case 'LIMIT':
                $support = in_array($this->config['driver'] , array('mysql', 'pgsql')) ? true : false;
                break;

            case 'TOP':
                $support = in_array($this->config['driver'] , array('sqlsrv', 'dblib')) ? true : false;
                break;

            case 'STRINGDATE':
                $support = in_array($this->config['driver'] , array('mysql', 'pgsql')) ? true : false;
                break;
        }

        return $support;
    }

    /**
     * Throw exception on error.
     *
     * @param string $notice                Explain to them what they screwed up
     *
     * @throws \Exception
     */
    protected function error($notice)
    {
        throw new \Exception($notice, null, null);
    }

}
