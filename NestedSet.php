<?php
//
// +----------------------------------------------------------------------+
// | PEAR :: DB_NestedSet                                                 |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |f
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Daniel Khan <dk@webcluster.at>                              |
// |          Jason Rust  <jason@rustyparts.com>                          |
// +----------------------------------------------------------------------+
// $Id$
//

// CREDITS:
// --------
// - Many thanks to Jason Rust for doing great improvements and cleanup work for the current release
// - Thanks to Kristian Koehntopp for publishing an explanation of the Nested Set
//   technique and for the great work he did and does for the php community
// - Thanks to Daniel T. Gorski for his great tutorial on www.develnet.org
// - Thanks to Hans Lellelid for suggesting support for MDB and for helping me with the
//   implementation
//   ...
// - Thanks to my parents for ... just kidding :]

require_once 'PEAR.php';

// {{{ constants

// Error and message codes
define('NESE_ERROR_RECURSION',    'E100');
define('NESE_ERROR_NODRIVER',   'E200');
define('NESE_ERROR_NOHANDLER',    'E300');
define('NESE_ERROR_TBLOCKED',     'E010');
define('NESE_MESSAGE_UNKNOWN',    'E0');
define('NESE_ERROR_NOTSUPPORTED', 'E1');
define('NESE_ERROR_PARAM_MISSING','E400');
define('NESE_ERROR_NOT_FOUND',    'E500');
define('NESE_ERROR_WRONG_MPARAM', 'E2');

// for moving a node before another
define('NESE_MOVE_BEFORE', 'BE');
// for moving a node after another
define('NESE_MOVE_AFTER', 'AF');
// for moving a node below another
define('NESE_MOVE_BELOW', 'SUB');


// }}}
// {{{ DB_NestedSet:: class

/**
* DB_NestedSet is a class for handling nested sets
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSet
* @version      $Revision$
* @access       public
*/

// }}}
class DB_NestedSet extends PEAR {
    // {{{ properties
    
    /**
    * @var array The field parameters of the table with the nested set. Format: 'realFieldName' => 'fieldId'
    * @access public
    */
    var $params = array(
    'STRID' => 'id',
    'ROOTID'=> 'rootid',
    'l'     => 'l',
    'r'     => 'r',
    'STREH' => 'norder',
    'LEVEL' => 'level',
    'STRNA' => 'name'
    );
    
    /**
    * @var array The above parameters flipped for easy access
    * @access private
    */
    var $flparams = array();
    
    /**
    * @var array An array of field ids that must exist in the table
    * Not used yet
    */
    var $requiredParams = array('id', 'rootid', 'l', 'r', 'norder', 'level');
    
    /**
    * @var string The table with the actual tree data
    * @access public
    */
    var $node_table = 'tb_nodes';
    
    /**
    * @var string The table to handle locking
    * @access public
    */
    var $lock_table = 'tb_locks';
    
    /**
    * @var string The table used for sequences
    * @access public
    */
    var $sequence_table;
    
    /**
    * Secondary order field.  Normally this is the order field, but can be changed to
    * something else (i.e. the name field so that the tree can be shown alphabetically)
    * @var string
    * @access public
    */
    var $secondarySort;
    
    /**
    * @var int The time to live of the lock
    * @access public
    */
    var $lockTTL = 1;
    
    /**
    * @var bool Enable debugging statements?
    * @access public
    */
    var $debug = 0;
    
    /**
    * @var bool Lock the structure of the table?
    * @access private
    */
    var $structureTableLock = false;
    
    /**
    * @var bool Skip the callback events?
    * @access private
    */
    var $skipCallbacks = false;
    
    /**
    * @var object cache Optional PEAR::Cache object
    * @access public
    */
    var $cache = false;
    
    /**
    * @var bool Do we want to use caching
    * @access private
    */
    var $_caching = false;
    
    /**
    * Used to determine the presence of listeners for an event in triggerEvent()
    *
    * If any event listeners are registered for an event, the event name will
    * have a key set in this array, otherwise, it will not be set.
    * @see triggerEvent()
    * @var array
    * @access private
    */
    var $_hasListeners = array();
    
    /**
    *
    * @var bool Temporary switch for cache
    * @access private
    */
    var $_restcache = false;
    
    var $_packagename   = 'DB_NestedSet';
    
    var $_majorversion   = 1;
    
    var $_minorversion   = 3;

    
    /**
    * @var array Map of error messages to their descriptions
    */
    var $messages = array(
    NESE_ERROR_RECURSION    => 'This operation would lead to a recursion',
    NESE_ERROR_TBLOCKED     => 'The structure Table is locked for another database operation, please retry.',
    NESE_ERROR_NODRIVER   => 'The selected database driver %s wasn\'t found',
    NESE_ERROR_NOTSUPPORTED => 'Method not supported yet',
    NESE_ERROR_NOHANDLER    => 'Event handler not found',
    NESE_ERROR_PARAM_MISSING=> 'Parameter missing',
    NESE_MESSAGE_UNKNOWN    => 'Unknown error or message',
    NESE_ERROR_NOT_FOUND    => '%s: Node %s not found',
    NESE_ERROR_WRONG_MPARAM        => '%s: %s'
    );
    
    /**
    * @var array The array of event listeners
    * @access public
    */
    var $eventListeners = array();
    
    // }}}
    // +---------------------------------------+
    // | Base methods                          |
    // +---------------------------------------+
    // {{{ constructor
    
    
    
    
    /**
    * Constructor
    *
    * @param array $params Database column fields which should be returned
    *
    * @access private
    * @return void
    */
    function DB_NestedSet($params) {
        
        if ($this->debug) {
            $this->_debugMessage('DB_NestedSet()');
        }
        $this->PEAR();
        if (is_array($params) && count($params) > 0) {
            $this->params = $params;
        }
        
        $this->flparams = array_flip($this->params);
        $this->sequence_table = $this->node_table . '_' . $this->flparams['id'];
        $this->secondarySort = $this->flparams['norder'];
        register_shutdown_function('_DB_NesetSet');
    }
    
    // }}}
    // {{{ factory
    
    /**
    * Handles the returning of a concrete instance of DB_NestedSet based on the driver.
    *
    * @param string $driver The driver, such as DB or MDB
    * @param string $dsn The dsn for connecting to the database
    * @param array $params The field name params for the node table
    *
    * @access public
    * @return object The DB_NestedSet object
    */
    function & factory($driver, $dsn, $params = array()) {
        
        $classname = 'DB_NestedSet_' . $driver;
        if (!class_exists($classname)) {
            $driverpath = dirname(__FILE__).'/NestedSet/'.$driver.'.php';
            if(!file_exists($driverpath) || !$driver) {
                return PEAR::raiseError("factory(): The database driver '$driver' wasn't found", NESE_ERROR_NODRIVER, PEAR_ERROR_TRIGGER, E_USER_ERROR);
            }
            include_once($driverpath);
        }
        return new $classname($dsn, $params);
    }
    
    // }}}
    // {{{ destructor
    
    /**
    * PEAR Destructor
    * Releases all locks
    * Closes open database connections
    *
    * @access private
    * @return void
    */
    function _DB_NestedSet() {
        
        if ($this->debug) {
            $this->_debugMessage('_DB_NestedSet()');
        }
        $this->_releaseLock();
    }
    
    // }}}
    // +----------------------------------------------+
    // | NestedSet manipulation and query methods     |
    // |----------------------------------------------+
    // | Querying the tree                            |
    // +----------------------------------------------+
    // {{{ getAllNodes()
    
    /**
    * Fetch the whole NestedSet
    *
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function getAllNodes($keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getAllNodes()');
        }
        $sql = sprintf('SELECT %s %s FROM %s %s %s ORDER BY %s.%s, %s.%s ASC',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->_addSQL($addSQL, 'append'),
        $this->node_table,
        $this->flparams['level'],
        $this->node_table,
        $this->secondarySort);
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
            }
        }
        return $nodeSet;
    }
    
    // }}}
    // {{{ getRootNodes()
    
    /**
    * Fetches the first level (the rootnodes) of the NestedSet
    *
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @see _addSQL()
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function getRootNodes($keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getRootNodes()');
        }
        $sql = sprintf('SELECT %s %s FROM %s %s WHERE %s.%s=%s.%s %s ORDER BY %s.%s ASC',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->node_table,
        $this->flparams['id'],
        $this->node_table,
        $this->flparams['rootid'],
        $this->_addSQL($addSQL, 'append'),
        $this->node_table,
        $this->secondarySort);
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
            }
        }
        return $nodeSet;
    }
    
    // }}}
    // {{{ getBranch()
    
    /**
    * Fetch the whole branch where a given node id is in
    *
    * @param int  $id The node ID
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @see _addSQL()
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function getBranch($id, $keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getBranch($id)');
        }
        if (!($thisnode = $this->pickNode($id, true))) {
            // FIXME Trigger Error
            return false;
        }
        
        $sql = sprintf('SELECT %s %s FROM %s %s WHERE %s.%s=%s %s ORDER BY %s.%s, %s.%s ASC',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->node_table,
        $this->flparams['rootid'],
        $this->db->quote($thisnode['rootid']),
        $this->_addSQL($addSQL, 'append'),
        $this->node_table,
        $this->flparams['level'],
        $this->node_table,
        $this->secondarySort);
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
            }
        }
        return $nodeSet;
    }
    
    // }}}
    // {{{ getParents()
    
    /**
    * Fetch the parents of a node given by id
    *
    * @param int  $id The node ID
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @see _addSQL()
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function getParents($id, $keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getParents($id)');
        }
        if (!($child = $this->pickNode($id, true))) {
            return false;
        }
        
        $sql = sprintf('SELECT %s %s FROM %s %s
                        WHERE %s.%s=%s AND %s.%s<%s AND %s.%s<%s AND %s.%s>%s %s
                        ORDER BY %s.%s ASC',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->node_table,
        $this->flparams['rootid'],
        $child['rootid'],
        $this->node_table,
        $this->flparams['level'],
        $child['level'],
        $this->node_table,
        $this->flparams['l'],
        $child['l'],
        $this->node_table,
        $this->flparams['r'],
        $child['r'],
        $this->_addSQL($addSQL, 'append'),
        $this->node_table,
        $this->flparams['level']);
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
            }
        }
        return $nodeSet;
    }
    
    function getParent($id, $keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getParents($id)');
        }
        if (!($child = $this->pickNode($id, true))) {
            return false;
        }
        
        if($child['id'] == $child['rootid']) {
            return false;
        }
        
        $addSQL['append'] = sprintf('AND %s.%s = %s',
        $this->node_table,
        $this->flparams['level'],
        $child['level']-1);
        
        $nodeSet =  $this->getParents($id, $keepAsArray, $aliasFields, $addSQL);
        
        if(!empty($nodeSet)) {
            $keys = array_keys($nodeSet);
            return $nodeSet[$keys[0]];
        } else {
            return false;
        }
    }
    
    function getSiblings($id, $keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getParents($id)');
        }
        
        if (!($sibling1 = $this->pickNode($id, true))) {
            return false;
        }
        
        $parent = $this->getParent($sibling1['id'], true);
        
        return $this->getChildren($parent, $keepAsArray, $aliasFields, $addSQL);
    }
    
    // }}}
    // {{{ getChildren()
    
    /**
    * Fetch the children _one level_ after of a node given by id
    *
    * @param int  $id The node ID
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param bool $forceNorder (optional) Force the result to be ordered by the norder
    *             param (as opposed to the value of secondary sort).  Used by the move and
    *             add methods.
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @see _addSQL()
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function getChildren($id, $keepAsArray = false, $aliasFields = true, $forceNorder = false, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getChildren($id)');
        }
        
        if(is_array($id) && count($id) == 1) {
            // Intended for internal use
            // to be able to pass a parent array from getSiblings()
            $parent = $id;
        } else {
            $parent = $this->pickNode($id, true);
        }
        if (!$parent || $parent['l'] == ($parent['r'] - 1)) {
            return false;
        }
        
        $orderBy = $forceNorder ? $this->flparams['norder'] : $this->secondarySort;
        $sql = sprintf('SELECT %s %s FROM %s %s
                        WHERE %s.%s=%s AND %s.%s=%s+1 AND %s.%s BETWEEN %s AND %s %s
                        ORDER BY %s.%s ASC',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->node_table,
        $this->flparams['rootid'],
        $this->db->quote($parent['rootid']),
        $this->node_table,
        $this->flparams['level'],
        $parent['level'],
        $this->node_table,
        $this->flparams['l'],
        $parent['l'],
        $parent['r'],
        $this->_addSQL($addSQL, 'append'),
        $this->node_table,
        $orderBy);
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
            }
        }
        return $nodeSet;
    }
    
    // }}}
    // {{{ getSubBranch()
    
    /**
    * Fetch all the children of a node given by id
    *
    * getChildren only queries the immediate children
    * getSubBranch returns all nodes below the given node
    *
    * @param string  $id The node ID
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @see _addSQL()
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function getSubBranch($id, $keepAsArray = false, $aliasFields = true, $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('getSubBranch($id)');
        }
        if (!($parent = $this->pickNode($id, true))) {
            return false;
        }
        
        $sql = sprintf('SELECT %s %s FROM %s %s WHERE %s.%s BETWEEN %s AND %s AND %s.%s=%s AND %s.%s!=%s %s',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->node_table,
        $this->flparams['l'],
        $parent['l'],
        $parent['r'],
        $this->node_table,
        $this->flparams['rootid'],
        $this->db->quote($parent['rootid']),
        $this->node_table,
        $this->flparams['id'],
        $this->db->quote($id),
        $this->_addSQL($addSQL, 'append'));
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
            }
        }
        return $nodeSet;
    }
    
    // }}}
    // {{{ pickNode()
    
    /**
    * Fetch the data of a node with the given id
    *
    * @param int  $id The node id of the node to fetch
    * @param bool $keepAsArray (optional) Keep the result as an array or transform it into
    *             a set of DB_NestedSet_Node objects?
    * @param bool $aliasFields (optional) Should we alias the fields so they are the names
    *             of the parameter keys, or leave them as is?
    * @param string $idfield (optional) Which field has to be compared with $id?
    *              This is can be used to pick a node by other values (e.g. it's name).
    * @param array $addSQL (optional) Array of additional params to pass to the query.
    *
    * @see _addSQL()
    * @access public
    * @return mixed False on error, or an array of nodes
    */
    function pickNode($id, $keepAsArray = false, $aliasFields = true, $idfield = 'id', $addSQL = array()) {
        if ($this->debug) {
            $this->_debugMessage('pickNode($id)');
        }
        
        if (is_object($id) && $id->id) {
            return $id;
        } elseif (is_array($id) && isset($id['id'])) {
            return $id;
        }
        
        $sql = sprintf('SELECT %s %s FROM %s %s WHERE %s.%s=%s %s',
        $this->_getSelectFields($aliasFields),
        $this->_addSQL($addSQL, 'cols'),
        $this->node_table,
        $this->_addSQL($addSQL, 'join'),
        $this->node_table,
        $this->flparams[$idfield],
        $this->db->quote($id),
        $this->_addSQL($addSQL, 'append'));
        
        if (!$this->_caching) {
            $nodeSet = $this->_processResultSet($sql, $keepAsArray, $aliasFields);
        } else {
            $nodeSet = $this->cache->call('DB_NestedSet->_processResultSet', $sql, $keepAsArray, $aliasFields);
        }
        
        $nsKey = false;
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeLoad'])) {
            // EVENT (nodeLoad)
            foreach (array_keys($nodeSet) as $key) {
                $this->triggerEvent('nodeLoad', $nodeSet[$key]);
                $nsKey = $key;
            }
        } else {
            foreach (array_keys($nodeSet) as $key) {
                $nsKey = $key;
            }
        }
        
        if (is_array($nodeSet) && $idfield != 'id') {
            $id = $nsKey;
        }
        
        return isset($nodeSet[$id]) ? $nodeSet[$id] : false;
    }
    
    // }}}
    // {{{ isParent()
    
    /**
    * See if a given node is a parent of another given node
    *
    * A node is considered to be a parent if it resides above the child
    * So it doesn't mean that the node has to be an immediate parent.
    * To get this information simply compare the levels of the two nodes
    * after you know that you have a parent relation.
    *
    * @param mixed  $parent The parent node as array or object
    * @param mixed  $child  The child node as array or object
    *
    * @access public
    * @return bool True if it's a parent
    */
    function isParent($parent, $child) {
        
        if ($this->debug) {
            $this->_debugMessage('isParent($parent, $child)');
        }
        
        if (!isset($parent)|| !isset($child)) {
            return false;
        }
        
        if (is_array($parent)) {
            $p_rootid   = $parent['rootid'];
            $p_l        = $parent['l'];
            $p_r        = $parent['r'];
            
        } elseif (is_object($parent)) {
            $p_rootid   = $parent->rootid;
            $p_l        = $parent->l;
            $p_r        = $parent->r;
        }
        
        if (is_array($child)) {
            $c_rootid   = $child['rootid'];
            $c_l        = $child['l'];
            $c_r        = $child['r'];
        } elseif (is_object($child)) {
            $c_rootid   = $child->rootid;
            $c_l        = $child->l;
            $c_r        = $child->r;
        }
        
        if (($p_rootid == $c_rootid) && ($p_l < $c_l && $p_r > $c_r)) {
            return true;
        }
        
        return false;
    }
    
    // }}}
    // {{{ _processResultSet()
    
    /**
    * Processes a DB result set by checking for a DB error and then transforming the result
    * into a set of DB_NestedSet_Node objects or leaving it as an array.
    *
    * @param string $sql The sql query to be done
    * @param bool $keepAsArray Keep the result as an array or transform it into a set of
    *             DB_NestedSet_Node objects?
    * @param bool $fieldsAreAliased Are the fields aliased?
    *
    * @access    private
    * @return mixed False on error or the transformed node set.
    */
    function _processResultSet($sql, $keepAsArray, $fieldsAreAliased) {
        $result = $this->db->getAll($sql);
        if ($this->_testFatalAbort($result, __FILE__, __LINE__)) {
            return false;
        }
        
        $nodes = array();
        $idKey = $fieldsAreAliased ? 'id' : $this->flparams['id'];
        foreach ($result as $row) {
            $node_id = $row[$idKey];
            if ($keepAsArray) {
                $nodes[$node_id] = $row;
            } else {
                // Create an instance of the node container
                $nodes[$node_id] =& new DB_NestedSet_Node($row);
            }
            
        }
        
        return $nodes;
    }
    
    // }}}
    // {{{ _getNodeObject()
    
    /**
    * Gets the node to work on based upon an id
    *
    * @param mixed $id The id which can be an object or integer
    *
    * @access private
    * @return mixed The node object for an id or false on error
    */
    function _getNodeObject($id) {
        if (!is_object($id) || !$id->id) {
            return $this->pickNode($id);
        }
        else {
            return $id;
        }
    }
    
    // }}}
    // {{{ _addSQL()
    
    /**
    * Adds a specific type of SQL to a query string
    *
    * @param array $addSQL The array of SQL strings to add.  Example value:
    *               $addSQL = array(
    *               'cols' => 'tb2.col2, tb2.col3',         // Additional tables/columns
    *               'join' => 'LEFT JOIN tb1 USING(STRID)', // Join statement
    *               'append' => 'GROUP by tb1.STRID');      // Group condition
    * @param string $type The type of SQL.  Can be 'cols', 'join', or 'append'.
    *
    * @access private
    * @return string The SQL, properly formatted
    */
    function _addSQL($addSQL, $type) {
        if (!isset($addSQL[$type])) {
            return '';
        }
        
        switch($type) {
            case 'cols':
            return ', ' . $addSQL[$type];
            default:
            return $addSQL[$type];
        }
    }
    
    // }}}
    // {{{ _getSelectFields()
    
    /**
    * Gets the select fields based on the params
    *
    * @param bool $aliasFields Should we alias the fields so they are the names of the
    *             parameter keys, or leave them as is?
    *
    * @access private
    * @return string A string of query fields to select
    */
    function _getSelectFields($aliasFields) {
        $queryFields = array();
        foreach ($this->params as $key => $val) {
            $tmp_field = $this->node_table . '.' . $key;
            if ($aliasFields) {
                $tmp_field .= ' AS ' . $val;
            }
            $queryFields[] = $tmp_field;
        }
        
        $fields = implode(', ', $queryFields);
        return $fields;
    }
    
    // }}}
    // +----------------------------------------------+
    // | NestedSet manipulation and query methods     |
    // |----------------------------------------------+
    // | insert / delete / update of nodes            |
    // +----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    // {{{ createRootNode()
    
    /**
    * Creates a new root node
    * Optionally it deletes the whole tree and creates one initial rootnode
    *
    * <pre>
    * +-- root1 [target]
    * |
    * +-- root2 [new]
    * |
    * +-- root3
    * </pre>
    *
    * @param array    $values      Hash with param => value pairs of the node (see $this->params)
    * @param integer  $id          ID of target node (the rootnode after which the node should be inserted)
    * @param bool     $first       Danger: Deletes and (re)init's the hole tree - sequences are reset
    *
    * @access public
    * @return int The node id
    */
    function createRootNode($values, $id = false, $first = false) {
        
        if ($this->debug) {
            //$this->_debugMessage('createRootNode()', func_get_args());
        }
        
        if(!$first && (!$id || !$parent = $this->pickNode($id, true))) {
            $epr = array('createRootNode()', $id);
            return $this->_raiseError(NESE_ERROR_NOT_FOUND, E_USER_ERROR, $epr);
        } elseif($first && $id) {
            $epr = array(
            'createRootNode()',
            '[id] AND [first] were passed - that doesn\'t make sense');
            $this->_raiseError(NESE_ERROR_WRONG_MPARAM, E_USER_WARNING, $epr);
        }
        
        // Try to aquire a table lock
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        $_flp = $this->flparams;
        
        $addval = array();
        $addval[$_flp['level']] = 1;
        
        // Shall we delete the existing tree (reinit)
        if ($first) {
            $sql = sprintf('DELETE FROM %s',
            $this->node_table);
            $this->db->query($sql);
            $this->db->dropSequence($this->sequence_table);
            // New order of the new node will be 1
            $addval[$_flp['norder']] = 1;
        } else {
            // Let's open a gap for the new node
            $addval[$_flp['norder']] = $parent['norder'] + 1;
        }
        
        // Sequence of node id (equals to root id in this case
        $addval[$_flp['rootid']] = $node_id = $addval[$_flp['id']] = $this->db->nextId($this->sequence_table);
        // Left/Right values for rootnodes
        $addval[$_flp['l']] = 1;
        $addval[$_flp['r']] = 2;
        // Transform the node data hash to a query
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        
        
        $sql = array();
        
        if (!$first) {
            // Open the gap
            $sql[] = sprintf('UPDATE %s SET %s=%s+1 WHERE %s=%s AND %s > %s',
            $this->node_table,
            $_flp['norder'],
            $_flp['norder'],
            $_flp['id'],
            $_flp['rootid'],
            $_flp['norder'],
            $parent['norder']);
        }
        
        // Insert the new node
        $sql[] = sprintf('INSERT INTO %s SET %s',        
        $this->node_table,
        $qr);
        
        
        for($i=0;$i<count($sql);$i++) {
            $res = $this->db->query($sql[$i]);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        
        
        // EVENT (nodeCreate)
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeCreate'])) {
            $thisnode = &$this->pickNode($node_id);
            $this->triggerEvent('nodeCreate', $this->pickNode($id));
        }
        return $node_id;
    }
    
    // }}}
    // {{{ createSubNode()
    
    /**
    * Creates a subnode
    *
    * <pre>
    * +-- root1
    * |
    * +-\ root2 [target]
    * | |
    * | |-- subnode1 [new]
    * |
    * +-- root3
    * </pre>
    *
    * @param integer    $id          Parent node ID
    * @param array      $values      Hash with param => value pairs of the node (see $this->params)
    *
    * @access public
    * @return mixed The node id or false on error
    */
    function createSubNode($id, $values) {
        if ($this->debug) {
            $this->_debugMessage('createSubNode($id, $values)');
        }
        // Try to aquire a table lock
        if(PEAR::isError($lock = $this->_setLock())) {
            return $lock;
        }
        
        // invalid parent id, bail out
        if (!($thisnode = $this->pickNode($id, true))) {
            $epr = array('createSubNode()', $id);
            return $this->_raiseError(NESE_ERROR_NOT_FOUND, E_USER_ERROR, $epr);
        }
        
        $_flp = $this->flparams;
        
        // Get the children of the target node
        $children = $this->getChildren($id, true);
        
        // We have children here
        if ($thisnode['r']-1 != $thisnode['l']) {
            // Get the last child
            $last = array_pop($children);
            // What we have to do is virtually an insert of a node after the last child
            // So we don't have to proceed creating a subnode
            $newNode = $this->createRightNode($last['id'], $values);
            return $newNode;
        }
        
        
        $sql[] = sprintf('
                UPDATE %s SET 
                %s=IF(%s>=%s, %s+2, %s),
                %s=IF(%s>=%s, %s+2, %s)
                WHERE %s=%s',
        $this->node_table,
        $_flp['l'],
        $_flp['l'],
        $thisnode['r'],
        $_flp['l'],
        $_flp['l'],
        $_flp['r'],
        $_flp['r'],
        $thisnode['r'],
        $_flp['r'],
        $_flp['r'],
        $_flp['rootid'],
        $thisnode['rootid']
        );
        
        $addval = array();
        $addval[$_flp['l']] = $thisnode['r'];
        $addval[$_flp['r']] = $thisnode['r'] + 1;
        $addval[$_flp['rootid']] = $thisnode['rootid'];
        $addval[$_flp['norder']] = 1;
        $addval[$_flp['level']] = $thisnode['level'] + 1;
        
        $node_id = $addval[$_flp['id']] = $this->db->nextId($this->sequence_table);
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        
        $sql[] = sprintf('INSERT INTO %s SET %s',
        $this->node_table,
        $qr);
        for($i=0;$i<count($sql);$i++) {
            $res = $this->db->query($sql[$i]);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        
        // EVENT (NodeCreate)
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeCreate'])) {
            $thisnode = $this->pickNode($node_id);
            $this->triggerEvent('nodeCreate', $this->pickNode($id));
        }
        return $node_id;
    }
    
    // }}}
    // {{{ createRightNode()
    
    function createLeftNode($id, $values) {
        
        if ($this->debug) {
            $this->_debugMessage('createLeftNode($target, $values)');
        }
        
        // invalid target node, bail out
        if (!($thisnode = $this->pickNode($id, true))) {
            $epr = array('createLeftNode()', $id);
            return $this->_raiseError(NESE_ERROR_NOT_FOUND, E_USER_ERROR, $epr);
        }
        
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        
        // If the target node is a rootnode we virtually want to create a new root node
        if ($thisnode['rootid'] == $thisnode['id']) {
            return $this->createRootNode($values, $id);
        }
        
        $flft = $this->flparams['l'];
        $frgt = $this->flparams['r'];
        $froot = $this->flparams['rootid'];
        $freh = $this->flparams['norder'];
        $fid = $this->flparams['id'];
        $flevel = $this->flparams['level'];
        
        $tb = $this->node_table;
        $addval = array();
        $parents = $this->getParents($id, true);
        $parent = array_pop($parents);
        
        
        $sql = array();
        
        
        $sql[] = sprintf('UPDATE %s SET %s=%s+1
                        WHERE 
                        %s=%s AND %s>=%s AND %s=%s AND %s BETWEEN %s AND %s',
        $tb,
        $freh,
        $freh,
        $froot,
        $thisnode['rootid'],
        $freh,
        $thisnode['norder'],
        $flevel,
        $thisnode['level'],
        $flft,
        $parent['l'],
        $parent['r']);
        
        
        // Update all nodes which have dependent left and right values
        $sql[] = sprintf('
                UPDATE %s SET 
                %s=IF(%s>=%s, %s+2, %s),
                %s=IF(%s>=%s, %s+2, %s)
                WHERE %s=%s',
        $tb,
        $flft,
        $flft,
        $thisnode['l'],
        $flft,
        $flft,
        $frgt,
        $frgt,
        $thisnode['r'],
        $frgt,
        $frgt,
        $froot,
        $thisnode['rootid']
        );
        
        
        
        $addval[$freh] = $thisnode['norder'];
        $addval[$flft] = $thisnode['l'];
        $addval[$frgt] = $thisnode['l']+1;
        $addval[$froot] = $thisnode['rootid'];
        $addval[$flevel] = $thisnode['level'];
        $node_id = $addval[$fid] = $this->db->nextId($this->sequence_table);
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        
        // Insert the new node
        $sql[] = "INSERT INTO $tb SET $qr";
        
        for($i=0;$i<count($sql);$i++) {
            $res = $this->db->query($sql[$i]);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        
        // EVENT (NodeCreate)
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeCreate'])) {
            $this->triggerEvent('nodeCreate', $this->pickNode($id));
        }
        return $node_id;
    }
    
    /**
    * Creates a node after a given node
    * <pre>
    * +-- root1
    * |
    * +-\ root2
    * | |
    * | |-- subnode1 [target]
    * | |-- subnode2 [new]
    * | |-- subnode3
    * |
    * +-- root3
    * </pre>
    *
    * @param int   $id        Target node ID
    * @param array      $values      Hash with param => value pairs of the node (see $this->params)
    *
    * @access public
    * @return object The new node object
    */
    function createRightNode($id, $values) {
        
        if ($this->debug) {
            $this->_debugMessage('createRightNode($target, $values)');
        }
        
        // invalid target node, bail out
        if (!($thisnode = $this->pickNode($id, true))) {
            $epr = array('createRightNode()', $id);
            return $this->_raiseError(NESE_ERROR_NOT_FOUND, E_USER_ERROR, $epr);
        }
        
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        
        // If the target node is a rootnode we virtually want to create a new root node
        if ($thisnode['rootid'] == $thisnode['id']) {
            return $this->createRootNode($values, $id);
        }
        
        $flft = $this->flparams['l'];
        $frgt = $this->flparams['r'];
        $froot = $this->flparams['rootid'];
        $freh = $this->flparams['norder'];
        $fid = $this->flparams['id'];
        $flevel = $this->flparams['level'];
        
        $tb = $this->node_table;
        $addval = array();
        $parents = $this->getParents($id, true);
        $parent = array_pop($parents);
        
        
        $sql = array();
        
        $sql[] = sprintf('UPDATE %s SET %s=%s+1
                        WHERE 
                        %s=%s AND %s>%s AND %s=%s AND %s BETWEEN %s AND %s',
        $tb,
        $freh,
        $freh,
        $froot,
        $thisnode['rootid'],
        $freh,
        $thisnode['norder'],
        $flevel,
        $thisnode['level'],
        $flft,
        $parent['l'],
        $parent['r']);
        
        
        // Update all nodes which have dependent left and right values
        
        
        $sql[] = sprintf('
                UPDATE %s SET 
                %s=IF(%s>%s, %s+2, %s),
                %s=IF(%s>%s, %s+2, %s)
                WHERE %s=%s',
        $tb,
        $flft,
        $flft,
        $thisnode['r'],
        $flft,
        $flft,
        $frgt,
        $frgt,
        $thisnode['r'],
        $frgt,
        $frgt,
        $froot,
        $thisnode['rootid']
        );
        
        $addval[$freh] = $thisnode['norder'] + 1;
        $addval[$flft] = $thisnode['r'] + 1;
        $addval[$frgt] = $thisnode['r'] + 2;
        $addval[$froot] = $thisnode['rootid'];
        $addval[$flevel] = $thisnode['level'];
        $node_id = $addval[$fid] = $this->db->nextId($this->sequence_table);
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        
        // Insert the new node
        $sql[] = "INSERT INTO $tb SET $qr";
        
        for($i=0;$i<count($sql);$i++) {
            $res = $this->db->query($sql[$i]);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        
        // EVENT (NodeCreate)
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeCreate'])) {
            $this->triggerEvent('nodeCreate', $this->pickNode($id));
        }
        return $node_id;
    }
    
    // }}}
    // {{{ deleteNode()
    
    /**
    * Deletes a node
    *
    * @param int $id ID of the node to be deleted
    *
    * @access public
    * @return bool True if the delete succeeds
    */
    function deleteNode($id) {
        
        if ($this->debug) {
            $this->_debugMessage("deleteNode($id)");
        }
        if (PEAR::isError($lock = $this->_setLock())) {
            return $lock;
        }
        
        if (!($thisnode = $this->pickNode($id, true))) {
            return false;
        }
        
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeDelete'])) {
            // EVENT (NodeDelete)
            $this->triggerEvent('nodeDelete', $this->pickNode($id));
        }
        
        $parents = $this->getParents($id, true);
        $parent = array_pop($parents);
        $plft = $parent['l'];
        $prgt = $parent['r'];
        
        $tb = $this->node_table;
        $flft = $this->flparams['l'];
        $frgt = $this->flparams['r'];
        $fid = $this->flparams['id'];
        $froot = $this->flparams['rootid'];
        $freh = $this->flparams['norder'];
        $flevel = $this->flparams['level'];
        
        $len = $thisnode['r'] - $thisnode['l'] + 1;
        
        
        $sql = array();
        
        // Delete the node
        $sql[] = sprintf('DELETE FROM %s WHERE %s BETWEEN %s AND %s AND %s=%s',
        $tb,
        $flft,
        $thisnode['l'],
        $thisnode['r'],
        $froot,
        $thisnode['rootid']
        );
        
        $sql[] = sprintf('DELETE FROM %s WHERE %s BETWEEN %s AND %s AND %s = %s',
        $tb,
        $flft,
        $thisnode['l'],
        $thisnode['r'],
        $froot,
        $thisnode['rootid']
        );
        
        
        if ($thisnode['id'] != $thisnode['rootid']) {
            
            
            // The node isn't a rootnode so close the gap
            
            $sql[] = sprintf('UPDATE %s SET
                            %s=IF(%s>%s, %s-%s, %s),
                            %s=IF(%s>%s, %s-%s, %s)
                            WHERE %s=%s AND
                            (%s>%s OR %s>%s)',
            $tb,
            $flft,
            $flft,
            $thisnode['l'],
            $flft,
            $len,
            $flft,
            $frgt,
            $frgt,
            $thisnode['l'],
            $frgt,
            $len,
            $frgt,
            $froot,
            $thisnode['rootid'],
            $flft,
            $thisnode['l'],
            $frgt,
            $thisnode['r']
            );
            
            // Re-order
            
            $sql[] = sprintf('UPDATE %s SET %s=%s-1 WHERE %s=%s AND %s=%s AND %s>%s AND %s BETWEEN %s AND %s',
            $tb,
            $freh,
            $freh,
            $froot,
            $thisnode['rootid'],
            $flevel,
            $thisnode['level'],
            $freh,
            $thisnode['norder'],
            $flft,
            $parent['l'],
            $parent['r']);
            
        } else {
            // A rootnode was deleted and we only have to close the gap inside the order
            $sql[] = sprintf('UPDATE %s SET %s=%s+1 WHERE %s=%s AND %s > %s',
            $tb,
            $freh,
            $freh,
            $froot,
            $fid,
            $freh,
            $thisnode['norder']);
        }
        for($i=0;$i<count($sql);$i++) {
            $res = $this->db->query($sql[$i]);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        return true;
    }
    
    // }}}
    // {{{ updateNode()
    
    /**
    * Changes the payload of a node
    *
    * @param int    $id Node ID
    * @param array  $values Hash with param => value pairs of the node (see $this->params)
    *
    * @access public
    * @return bool True if the update is successful
    */
    function updateNode($id, $values) {
        if ($this->debug) {
            $this->_debugMessage('updateNode($id, $values)');
        }
        if (PEAR::isError($lock = $this->_setLock())) {
            return $lock;
        }
        
        if (!($thisnode =& $this->pickNode($id))) {
            return false;
        }
        
        $eparams = array('values' => $values);
        if (!$this->skipCallbacks && isset($this->_hasListeners['nodeUpdate'])) {
            // EVENT (NodeUpdate)
            $this->triggerEvent('nodeUpdate', $this->pickNode($id), $eparams);
        }
        $fid = $this->flparams['id'];
        $addvalues = array();
        if (!$qr = $this->_values2Query($values, $addvalues)) {
            return false;
        }
        
        $sql = sprintf('UPDATE %s SET %s WHERE %s = %s',
        $this->node_table,
        $qr,
        $fid,
        $id);
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        return true;
    }
    
    
    
    // }}}
    // +----------------------------------------------+
    // | Moving and copying                           |
    // |----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    // {{{ moveTree()
    
    /**
    * Wrapper for node moving and copying
    *
    * @param int    $id Source ID
    * @param int    $target Target ID
    * @param array  $pos Position (use one of the NESE_MOVE_* constants)
    * @param bool   $copy Shall we create a copy
    *
    * @see _moveInsideLevel
    * @see _moveAcross
    * @see _moveRoot2Root
    * @access public
    * @return int ID of the moved node or false on error
    */
    function moveTree($id, $targetid, $pos, $copy = false) {
        
        if ($this->debug) {
            $this->_debugMessage('moveTree($id, $target, $pos, $copy = false)');
        }
        if($id == $targetid && !$copy) {
            // TRIGGER BOGUS MESSAGE
            return false;
        }
        if (PEAR::isError($lock = $this->_setLock())) {
            return $lock;
        }
        
        $this->_relations = array();
        // This operations don't need callbacks except the copy handler
        // which ignores this setting
        $this->skipCallbacks = true;
        // Get information about source and target
        if (!($source = $this->pickNode($id, true))) {
            $this->raiseError("Node id: $id not found", NESE_ERROR_NOT_FOUND, PEAR_ERROR_TRIGGER, E_USER_ERROR);
            return false;
        }
        
        if (!($target = $this->pickNode($targetid, true))) {
            $this->raiseError("Target id: $targetid not found", NESE_ERROR_NOT_FOUND, PEAR_ERROR_TRIGGER, E_USER_ERROR);
            return false;
        }
        
        if(!$copy) {
            // We have a recursion - let's stop
            if (($target['rootid'] == $source['rootid']) &&
            (($source['l'] <= $target['l']) &&
            ($source['r'] >= $target['r']))) {
                
                return new PEAR_Error($this->_getMessage(NESE_ERROR_RECURSION),NESE_ERROR_RECURSION);
            }
            
            // Insert/move before or after
            
            if (($source['rootid'] == $source['id']) &&
            ($target['rootid'] == $target['id'])) {
                // We have to move a rootnode which is different from moving inside a tree
                return $this->_moveRoot2Root($source, $target, $pos, $copy);
            }
        } elseif(($target['rootid'] == $source['rootid']) &&
                (($source['l'] < $target['l']) &&
                ($source['r'] > $target['r']))) {
                return new PEAR_Error($this->_getMessage(NESE_ERROR_RECURSION),NESE_ERROR_RECURSION);
        }
        
        // We have to move between different levels and maybe subtrees - let's rock ;)
        $this->_moveAcross($source, $target, $pos);
        $this->_moveCleanup($copy);
    }
    
    // }}}
    // {{{ _moveAcross()
    
    /**
    * Moves nodes and trees to other subtrees or levels
    *
    * <pre>
    * [+] <--------------------------------+
    * +-[\] root1 [target]                 |
    *     <-------------------------+      |p
    * +-\ root2                     |      |
    * | |                           |      |
    * | |-- subnode1 [target]       |      |B
    * | |-- subnode2 [new]          |S     |E
    * | |-- subnode3                |U     |F
    * |                             |B     |O
    * +-\ root3                     |      |R
    *   |-- subnode 3.1             |      |E
    *   |-\ subnode 3.2 [source] >--+------+
    *     |-- subnode 3.2.1
    *</pre>
    *
    * @param     object NodeCT $source   Source node
    * @param     object NodeCT $target   Target node
    * @param     string    $pos          Position [SUBnode/BEfore]
    * @param     bool         $copy                Shall we create a copy
    *
    * @access    private
    * @see        moveTree
    * @see        _r_moveAcross
    * @see        _moveCleanup
    */
    function _moveAcross($source, $target, $pos) {
        if ($this->debug) {
            $this->_debugMessage("_moveAcross($source, $target, $pos, $copy = false)");
        }
        if (PEAR::isError($lock = $this->_setLock())) {
            return $lock;
        }
        
        // Get the current data from a node and exclude the id params which will be changed
        // because of the node move
        $values = array();
        foreach($this->params as $key => $val) {
            if ($source[$val] && !in_array($val, $this->requiredParams)) {
                $values[$key] = trim($source[$val]);
            }
        }
        
        switch($pos) {
            
            case NESE_MOVE_BEFORE:
            $clone_id = $this->createLeftNode($target['id'], $values);
            break;
            
            case NESE_MOVE_AFTER:
            $clone_id = $this->createRightNode($target['id'], $values);
            break;
            
            case NESE_MOVE_BELOW:
            $clone_id = $this->createSubNode($target['id'], $values);
            break;
        }
        
        
        $children = $this->getChildren($source['id'], true);
        
        
        if ($children) {
            $pos = NESE_MOVE_BELOW;
            $sclone_id = $clone_id;
            // Recurse through the child nodes
            foreach($children AS $cid => $child) {
                $sclone = $this->pickNode($sclone_id, true);
                $sclone_id = $this->_moveAcross($child, $sclone, $pos);
                
                $pos = NESE_MOVE_AFTER;
            }
        }
        
        $this->_relations[$source['id']] = $clone_id;
        return $clone_id;
    }
    
    // }}}
    // {{{ _r_moveAcross()
    
    
    // }}}
    // {{{ _moveCleanup()
    
    /**
    * Deletes the old subtree (node) and writes the node id's into the cloned tree
    *
    *
    * @param     array    $relations        Hash in der Form $h[alteid]=neueid
    * @param     array    $copy                     Are we in copy mode?
    * @access    private
    */
    function _moveCleanup($copy = false) {
        
        $relations = $this->_relations;
        if ($this->debug) {
            $this->_debugMessage('_moveCleanup($relations, $copy = false)');
        }
        if (PEAR::isError($lock = $this->_setLock())) {
            return $lock;
        }
        
        $deletes = array();
        $updates = array();
        $tb = $this->node_table;
        $fid = $this->flparams['id'];
        $froot = $this->flparams['rootid'];
        foreach($relations AS $key => $val) {
            $clone = $this->pickNode($val);
            if ($copy) {
                // EVENT (NodeCopy)
                
                $eparams = array('clone' => $clone);
                
                if (!$this->skipCallbacks && isset($this->_hasListeners['nodeCopy'])) {
                    $thisnode =& $this->pickNode($key);
                    $this->triggerEvent('nodeCopy', $this->pickNode($id), $eparams);
                }
                continue;
            }
            
            // No callbacks here because the node itself doesn't get changed
            // Only it's position
            // If one needs a callback here please let me know
            
            $deletes[] = $key;
            // It's isn't a rootnode
            if ($clone->id != $clone->rootid) {
                
                
                $sql = sprintf('UPDATE %s SET %s=%s WHERE %s = %s',
                $this->node_table,
                $fid,
                $key,
                $fid,
                $val);
                $updates[] = $sql;
            } else {
                
                $sql = "UPDATE $tb SET
                            $fid=" . $this->db->quote($key) . ",
                            $froot=" . $this->db->quote($key) . "
                        WHERE $fid=" . $this->db->quote($val);
                $updates[] = $sql;
                $orootid = $clone->rootid;
                $sql = "UPDATE $tb
                        SET $froot=" . $this->db->quote($key) . "
                        WHERE $froot=" . $this->db->quote($orootid);
                $updates[] = $sql;
            }
            $this->skipCallbacks = false;
        }
        
        if(!empty($deletes)) {
            for($i=0;$i<count($deletes);$i++) {
                $this->deleteNode($deletes[$i]);
            }
        }
        if(!empty($updates)) {
            for($i=0;$i<count($updates);$i++) {
               $res = $this->db->query($updates[$i]);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        
        return true;
    }
    
    // }}}
    // {{{ _moveInsideLevel()
    
    // }}}
    // {{{ _moveRoot2Root()
    
    /**
    * Moves rootnodes
    *
    * <pre>
    * +-- root1
    * |
    * +-\ root2
    * | |
    * | |-- subnode1 [target]
    * | |-- subnode2 [new]
    * | |-- subnode3
    * |
    * +-\ root3
    *  [|]  <-----------------------+
    *   |-- subnode 3.1 [target]    |
    *   |-\ subnode 3.2 [source] >--+
    *     |-- subnode 3.2.1
    * </pre>
    *
    * @param     object NodeCT $source    Source
    * @param     object NodeCT $target    Target
    * @param     object NodeCT $target    Parent
    * @param     string $pos              BEfore | AFter
    * @param     string $copy             Copy mode?
    * @access    private
    * @see        moveTree
    */
    function _moveRoot2Root($source, $target, $pos, $copy) {
        
        if ($this->debug) {
            $this->_debugMessage('_moveRoot2Root($source, $target, $pos, $copy)');
        }
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        $tb = $this->node_table;
        $fid = $this->flparams['id'];
        $froot = $this->flparams['rootid'];
        $freh = $this->flparams['norder'];
        $s_order = $source['norder'];
        $t_order = $target['norder'];
        $s_id = $source['id'];
        $t_id = $target['id'];
        
        
        if ($s_order < $t_order) {
            if ($pos == NESE_MOVE_BEFORE) {
                $sql = "UPDATE $tb SET $freh=$freh-1
                        WHERE $freh BETWEEN $s_order AND $t_order AND
                            $fid!=$t_id AND
                            $fid!=$s_id AND
                            $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                $sql = "UPDATE $tb SET $freh=$t_order -1 WHERE $fid=$s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
            elseif($pos == NESE_MOVE_AFTER) {
                
                $sql = "UPDATE $tb SET $freh=$freh-1
                        WHERE $freh BETWEEN $s_order AND $t_order AND
                            $fid!=$s_id AND
                            $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET $freh=$t_order WHERE $fid=$s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        
        if ($s_order > $t_order) {
            if ($pos == NESE_MOVE_BEFORE) {
                $sql = "UPDATE $tb SET $freh=$freh+1
                        WHERE $freh BETWEEN $t_order AND $s_order AND
                            $fid != $s_id AND
                            $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET $freh=$t_order WHERE $fid=$s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
            elseif ($pos == NESE_MOVE_AFTER) {
                $sql = "UPDATE $tb SET $freh=$freh+1
                        WHERE $freh BETWEEN $t_order AND $s_order AND
                        $fid!=$t_id AND
                        $fid!=$s_id AND
                        $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET $freh=$t_order+1 WHERE $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        
        return $source->id;
    }
    
    // }}}
    // +-----------------------+
    // | Helper methods        |
    // +-----------------------+
    // {{{ _testFatalAbort()
    
    /**
    * Error Handler
    *
    * Tests if a given ressource is a PEAR error object
    * ans raises a fatal error in case of an error object
    *
    * @param        object  PEAR::Error $errobj     The object to test
    * @param        string  $file   The filename wher the error occured
    * @param        int     $line   The line number of the error
    * @return   void
    * @access private
    */
    function _testFatalAbort($errobj, $file, $line) {
        if (!PEAR::isError($errobj)) {
            return false;
        }
        
        if ($this->debug) {
            $this->_debugMessage('_testFatalAbort($errobj, $file, $line)');
        }
        if ($this->debug) {
            $message = $errobj->getUserInfo();
            $code = $errobj->getCode();
            $msg = "$message ($code) in file $file at line $line";
        } else {
            $msg = $errobj->getMessage();
            $code = $errobj->getCode();     }
            
            $this->raiseError($msg, $code, PEAR_ERROR_TRIGGER, E_USER_ERROR);
    }
    
    // }}}
    // {{{ addListener()
    
    /**
    * Add an event listener
    *
    * Adds an event listener and returns an ID for it
    *
    * @param        string $event           The ivent name
    * @param        string  $listener       The listener object
    * @return   string
    * @access public
    */
    function addListener($event, &$listener) {
        $listenerID = uniqid('el');
        $this->eventListeners[$event][$listenerID] =& $listener;
        $this->_hasListeners[$event] = true;
        return $listenerID;
    }
    
    // }}}
    // {{{ removeListener()
    
    /**
    * Removes an event listener
    *
    * Removes the event listener with the given ID
    *
    * @param        string $event           The ivent name
    * @param        string  $listenerID     The listener's ID
    * @return   bool
    * @access public
    */
    function removeListener($event, $listenerID) {
        unset($this->eventListeners[$event][$listenerID]);
        if (!isset($this->eventListeners[$event]) ||
        !is_array($this->eventListeners[$event]) ||
        count($this->eventListeners[$event]) == 0) {
            unset($this->_hasListeners[$event]);
        }
        return true;
    }
    
    // }}}
    // {{{ triggerEvent()
    
    /**
    * Triggers and event an calls the event listeners
    *
    * @param        string $event   The Event that occured
    * @param        object node $node A Reference to the node object which was subject to changes
    * @param        array $eparams  A associative array of params which may be needed by the handler
    * @return   bool
    * @access public
    */
    function triggerEvent($event, &$node, $eparams = false) {
        if ($this->skipCallbacks || !isset($this->_hasListeners[$event])) {
            return false;
        }
        
        foreach($this->eventListeners[$event] as $key => $val) {
            if (!method_exists($val, 'callEvent')) {
                return new PEAR_Error($this->_getMessage(NESE_ERROR_NOHANDLER), NESE_ERROR_NOHANDLER);
            }
            
            $val->callEvent($event, $node, $eparams);
        }
        
        return true;
    }
    
    // }}}
    
    
    function api() {
        return array(
            'package:'=>$this->_packagename,
            'majorversion'=>$this->_majorversion,
            'minorversion'=>$this->_minorversion,
            'version'=>sprintf('%s.%s',$this->_majorversion, $this->_minorversion),
            'revision'=>str_replace('$', '',"$Revision$")
        );   
    }
    
    
    // {{{ setAttr()
    
    /**
    * Sets an object attribute
    *
    * @param        array $attr     An associative array with attributes
    *
    * @return   bool
    * @access public
    */
    function setAttr($attr) {
        static $hasSetSequence;
        if (!isset($hasSetSequence)) {
            $hasSetSequence = false;
        }
        
        if (!is_array($attr) || count($attr) == 0) {
            return false;
        }
        
        foreach ($attr as $key => $val) {
            $this->$key = $val;
            if ($key == 'sequence_table') {
                $hasSetSequence = true;
            }
            
            // only update sequence to reflect new table if they haven't set it manually
            if (!$hasSetSequence && $key == 'node_table') {
                $this->sequence_table = $this->node_table . '_' . $this->flparams['id'];
            }
            if($key == 'cache' && is_object($val)) {
                $this->_caching = true;
                $GLOBALS['DB_NestedSet'] = & $this;
            }
        }
        
        return true;
    }
    
    // }}}
    // {{{ setDbOption()
    
    /**
    * Sets a db option.  Example, setting the sequence table format
    *
    * @var string $option The option to set
    * @var string $val The value of the option
    *
    * @access public
    * @return void
    */
    function setDbOption($option, $val) {
        $this->db->setOption($option, $val);
    }
    
    // }}}
    // {{{ testLock()
    
    /**
    * Tests if a database lock is set
    *
    * @access public
    */
    function testLock() {
        if ($this->debug) {
            $this->_debugMessage('testLock()');
        }
        if($lockID = $this->structureTableLock) {
            return $lockID;
        }
        
        $this->_lockGC();
        $tb = $this->lock_table;
        $stb = $this->node_table;
        
        $sql = "SELECT lockID FROM $tb WHERE lockTable=" . $this->db->quote($stb);
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        
        if ($res->numRows()) {
            return new PEAR_Error($this->_getMessage(NESE_ERROR_TBLOCKED),NESE_ERROR_TBLOCKED);
        }
        
        return false;
    }
    
    // }}}
    // {{{ _setLock()
    
    /**
    * @access private
    */
    function _setLock() {
        $lock = $this->testLock();
        if(PEAR::isError($lock)) {
            return $lock;
        }
        
        if ($this->debug) {
            $this->_debugMessage('_setLock()');
        }
        if($this->_caching) {
            @$this->cache->flush('function_cache');
            $this->_caching = false;
            $this->_restcache = true;
        }
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $stamp = time();
        if (!$lockID = $this->structureTableLock) {
            $lockID = $this->structureTableLock = uniqid('lck-');
            $sql = "INSERT INTO $tb SET
                        lockID=" . $this->db->quote($lockID) . ",
                        lockTable=" . $this->db->quote($stb) . ",
                        lockStamp=" . $this->db->quote($stamp);
        } else {
            $sql = "UPDATE $tb SET lockStamp=" . $this->db->quote($stamp) . "
                    WHERE lockID=" . $this->db->quote($lockID) . " AND
                        lockTable=" . $this->db->quote($stb);
        }
        
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        return $lockID;
    }
    
    // }}}
    // {{{ _releaseLock()
    
    /**
    * @access private
    */
    function _releaseLock() {
        if ($this->debug) {
            $this->_debugMessage('_releaseLock()');
        }
        if (!$lockID = $this->structureTableLock) {
            return false;
        }
        
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $sql = "DELETE FROM $tb
                WHERE lockTable=" . $this->db->quote($stb) . " AND
                    lockID=" . $this->db->quote($lockID);
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        $this->structureTableLock = false;
        if($this->_restcache) {
            $this->_caching = true;
            $this->_restcache = false;
        }
        return true;
    }
    
    // }}}
    // {{{ _lockGC()
    
    /**
    * @access private
    */
    function _lockGC() {
        if ($this->debug) {
            $this->_debugMessage('_lockGC()');
        }
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $lockTTL = time() - $this->lockTTL;
        $sql = "DELETE FROM $tb
                WHERE lockTable=" . $this->db->quote($stb) . " AND
                    lockStamp < $lockTTL";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
    }
    
    // }}}
    // {{{ _values2Query()
    
    /**
    * @access private
    */
    function _values2Query($values, $addval = false) {
        if ($this->debug) {
            $this->_debugMessage('_values2Query($values, $addval = false)');
        }
        if (is_array($addval)) {
            $values = $values + $addval;
        }
        
        $arq = array();
        foreach($values AS $key => $val) {
            $k = trim($key);
            $v = trim($val);
            if ($k) {
                
                $arq[] = "$k=" . $this->db->quote($v);
            }
        }
        
        if (!is_array($arq) || count($arq) == 0) {
            return false;
        }
        
        $query = implode(', ', $arq);
        return $query;
    }
    
    // }}}
    // {{{ _debugMessage()
    
    /**
    * @access private
    */
    function _debugMessage($msg) {
        if ($this->debug) {
            $time = $this->_getmicrotime();
            echo "$time::Debug:: $msg<br />\n";
        }
    }
    
    // }}}
    // {{{ _getMessage()
    
    /**
    * @access private
    */
    function _getMessage($code) {
        if ($this->debug) {
            $this->_debugMessage('_getMessage($code)');
        }
        return isset($this->messages[$code]) ? $this->messages[$code] : $this->messages[NESE_MESSAGE_UNKNOWN];
        
    }
    
    function _raiseError($code, $option, $epr=array()) {
        $message = vsprintf($this->_getMessage($code), $epr);
        return PEAR::raiseError($message, $code, PEAR_ERROR_TRIGGER, $option);
    }
    
    // }}}
    // {{{ _getmicrotime()
    
    /**
    * @access private
    */
    function _getmicrotime() {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }
    
    // }}}
    
}
// {{{ DB_NestedSet_Node:: class

/**
* Generic class for node objects
*
* @autor Daniel Khan <dk@webcluster.at>;
* @version $Revision$
* @package DB_NestedSet
*
* @access private
*/

// }}}
class DB_NestedSet_Node {
    // {{{ constructor
    
    /**
    * Constructor
    */
    function DB_NestedSet_Node($data) {
        if (!is_array($data) || count($data) == 0) {
            return new PEAR_ERROR($data, NESE_ERROR_PARAM_MISSING);
        }
        
        $this->setAttr($data);
        return true;
    }
    
    // }}}
    // {{{ setAttr()
    
    function setAttr($data) {
        if(!is_array($data) || count($data) == 0) {
            return false;
        }
        
        foreach ($data as $key => $val) {
            $this->$key = $val;
        }
    }
    
    // }}}
    
}
?>