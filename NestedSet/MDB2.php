<?php
//
// +----------------------------------------------------------------------+
// | PEAR :: DB_NestedSet_MDB2                                            |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Lorenzo Alberton <l dot alberton at quipo dot it>           |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'MDB2.php';

/**
 * Wrapper class for PEAR::MDB2
 *
 * @author       Lorenzo Alberton <l dot alberton at quipo dot it>
 * @package      DB_NestedSet
 * @version      $Revision$
 * @access       public
 */
class DB_NestedSet_MDB2 extends DB_NestedSet
{
    // {{{ class properties

    /**
     * @var object The MDB2 object
     */
    var $db;

    // }}}
    // {{{ constructor

    /**
     * Constructor
     *
     * @param mixed $dsn DSN as PEAR dsn URI or dsn Array
     * @param array $params Database column fields which should be returned
     */
    function & DB_NestedSet_MDB2(&$dsn, $params = array())
    {
        $this->_debugMessage('DB_NestedSet_MDB2($dsn, $params = array())');
        $this->DB_NestedSet($params);
        $this->db =& $this->_db_Connect($dsn);
        $this->db->setFetchMode(MDB2_FETCHMODE_ASSOC);
    }

    // }}}
    // {{{ destructor

    /**
     * Destructor
     */
    function _DB_NestedSet_MDB2()
    {
        $this->_debugMessage('_DB_NestedSet_MDB2()');
        $this->_DB_NestedSet();
        $this->_db_Disconnect();
    }

    // }}}
    // {{{ _db_Connect()

    /**
     * Connects to the db
     *
     * @return object DB The database object
     * @access private
     */
    function &_db_Connect(&$dsn)
    {
        $this->_debugMessage('_db_Connect($dsn)');
        if (is_object($this->db)) {
            return $this->db;
        }

        if (is_object($dsn)) {
            return $dsn;
        }

        $db =& MDB2::connect($dsn);
        $this->_testFatalAbort($db, __FILE__, __LINE__);

        return $db;
    }

    // }}}

    // {{{ _query()
    function _query($sql) {
      return $this->db->query($sql);
    }

    // {{{ _isDBError()

    /**
     * @param mixed $err
     * @return boolean
     * @access private
     */
    function _isDBError($err)
    {
        if (!MDB2::isError($err)) {
            return false;
        }
        return true;
    }

    // }}}
    // {{{ _nextId()

    /**
     * @param string $sequence sequence name
     * @return integer
     */
    function _nextId($sequence)
    {
        return $this->db->nextId($sequence);
    }

    // }}}
    // {{{ _dropSequence()

    /**
     * @param string $sequence sequence name
     * @return mixed
     * @access private
     */
    function _dropSequence($sequence)
    {
        $this->db->loadModule('manager');
        return $this->db->manager->dropSequence($sequence);
    }

    // }}}
    // {{{ _getOne()

    /**
     * @param string $sql SQL query
     * @return mixed
     * @access private
     */
    function _getOne($sql)
    {
        return $this->db->queryOne($sql);
    }

    // }}}
    // {{{ _getAll()

    /**
     * @param string $sql SQL query
     * @return mixed
     * @access private
     */
    function _getAll($sql)
    {
        return $this->db->queryAll($sql, null, MDB2_FETCHMODE_ASSOC);
    }

    // }}}
    // {{{ _numRows()

    /**
     * @param object db resource
     * @return integer
     * @access private
     */
    function _numRows($res)
    {
        return $res->numRows();
    }

    // }}}
    // {{{ _quote()

    /**
     * @param string $str string to be quoted
     * @return string
     * @access private
     */
    function _quote($str)
    {
        return $this->db->quote($str, 'text');
    }

    // }}}
    // {{{ _quoteIdentifier()

    /**
     * Unsupported! Will work as soon as MDB supports quoteIdentifier()
     *
     * @param string $sql SQL query
     * @return mixed
     * @access private
     */
    function _quoteIdentifier($str)
    {
        if (method_exists($this->db, 'quoteIdentifier')) {
            return $this->db->quoteIdentifier($str);
        }
        return $str;
    }

  // }}}
    // {{{ _db_Disconnect()

    /**
     * Disconnects from db
     *
     * @return void
     * @access private
     */
    function _db_Disconnect()
    {
        $this->_debugMessage('_db_Disconnect()');
        if (is_object($this->db)) {
            @$this->db->disconnect();
        }
        return true;
    }

    // }}}
}
?>