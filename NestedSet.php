<?php

/**
// +----------------------------------------------------------------------+
// | PEAR :: DB_NestedSet                                                    |
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
// | Authors: Daniel Khan <dk@webcluster.at>                              |
// +----------------------------------------------------------------------+
//
// $Id$
//
//
*/

// CREDITS:
// --------
// - Thanks to Kristian Koehntopp for publishing an explanation of the Nested Set
//   technique and for the great work he did and does for the php community
// - Thanks to Daniel T. Gorski for his great tutorial on www.develnet.org
// - Thanks to Hans Lellelid for suggesting support for MDB and for helping me with the
//   implementation
//   ...
// - Thanks to my parents for ... just kidding :]

// Require needed PEAR classes
require_once 'PEAR.php';



// Fetch the whole tree(s)
define("NESE_FETCH_ALLNODES", 1);
// Fetch all rootnodes
define("NESE_FETCH_ROOTNODES", 2);
// Fetch a node by a given ID
define("NESE_FETCH_NODEBYID", 3);
// Fetch parents of a node
define("NESE_FETCH_PARENTS", 4);
// Fetch immediate children of a node
define("NESE_FETCH_CHILDREN", 5);
// Fetch all children of a node including the node itself
define("NESE_FETCH_SUBBRANCH", 6);
// Fetch the whole branch where a given node id is in
define("NESE_FETCH_BRANCH", 7);


// Error and message codes
define("NESE_ERROR_RECURSION",    'E100');
define("NESE_DRIVER_NOT_FOUND",   'E200');
define("NESE_ERROR_NOHANDLER",    'E300');
define("NESE_ERROR_TBLOCKED",     'E010');
define("NESE_MESSAGE_UNKNOWN",    'E0');
define("NESE_ERROR_NOTSUPPORTED", 'E1');



/**
* DB_NestedSet is a class for handling nested sets
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      NestedSet
* @version      $Revision$
* @access       public
*/
Class DB_NestedSet extends PEAR {
    
    var $params =   array(
    
    "STRID"         =>      "id",     
    "ROOTID"        =>      "rootid", 
    "l"                 =>  "l",     
    "r"                 =>  "r",  
    "STREH"         =>      "norder", 
    "LEVEL"         =>      "level", 
    "STRNA"         =>      "name"
    );
    
    var $node_table                 = "tb_nodes";
    var $lock_table                 = "tb_locks";
    var $lockTTL                    = 2;
    var $flparams                   = array();
    var $debug                              = false;
    var $structureTableLock = false;
    var $skipCallbacks              = false;
    
    
    var $messages                   = array( NESE_ERROR_RECURSION   => 'This operation would lead to a recursion',
    NESE_ERROR_TBLOCKED     => 'The structure Table is locked for another database operation, please retry.',
    NESE_DRIVER_NOT_FOUND   => "The selected database driver wasn't found",
    NESE_ERROR_NOTSUPPORTED => 'Method not supported yet',
    NESE_ERROR_NOHANDLER    => 'Event handler not found',
    NESE_MESSAGE_UNKNOWN    => 'Unknown error or message');
    
    // +---------------------------------------+
    // | Base methods                          |
    // +---------------------------------------+
    
    /**
    * Constructor
    *
    * @param    dsn    string    DSN we want connect to
    * @return void
    * @access private
    */
    function DB_NestedSet() {
        $this->_debugMessage("DB_NestedSet()");
        $this->PEAR();
    }
    
    function &factory($driver, $dsn, $params = array())
    {
        $driverpath = dirname(__FILE__).'/NestedSet/'. $driver.'.php';
        
        if(!file_exists($driverpath) || !$driver) {
            return new PEAR_Error('E200',"The database driver '$driver' wasn't found");
        }
        
        include_once($driverpath);
        $classname = 'DB_NestedSet_' . $driver;
        
        return new $classname($dsn, $params);
    }
    
    /**
    * PEAR Destructor
    * Releases all locks
    * Closes open database connections
    *
    * @return void
    * @access private
    */
    function _DB_NestedSet() {
        $this->_debugMessage("_DB_NestedSet()");
        $this->_releaseLock();
    }
    
    // +----------------------------------------------+
    // | NestedSet manipulation and query methods     |
    // |----------------------------------------------+
    // | Querying the tree                            |
    // +----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    
    /**
    * Fetch the whole NestedSet
    *
    * @access public
    * @return array         An array of the node objects inside the database
    * @see _getAllNodes()
    */
    function getAllNodes() {
        $this->_debugMessage("getAllNodes()");
        return $this->_getNodes(NESE_FETCH_ALLNODES);
    }
    
    /**
    * Fetch all rootnodes
    * Fetches the first level (the rootnodes) of the NestedSet
    *
    * @access public
    * @return array         An array of the root node objects inside the database
    * @see _getRootNodes()
    */
    function getRootNodes() {
        $this->_debugMessage("getRootNodes()");
        return $this->_getNodes(NESE_FETCH_ROOTNODES);
    }
    
    /**
    * Fetch the whole branch where a given node id is in
    *
    * @param        string  $id     The node ID
    * @access public
    * @return array         An array of the node objects inside the branch
    * @see _getBranch()
    */
    function getBranch($id) {
        $this->_debugMessage("getBranch($id)");
        return $this->_getNodes(NESE_FETCH_BRANCH, $id);
    }
    
    /**
    * Fetch the parents of a node given by id
    *
    * @param        string  $id     The node ID
    * @access public
    * @return array         An array of the parent node objects
    * @see _getParents()
    */
    function getParents($id) {
        $this->_debugMessage("getParents($id)");
        return $this->_getNodes(NESE_FETCH_PARENTS, $id);
    }
    
    /**
    * Fetch the children _one level_ after of a node given by id
    *
    * @param        string  $id     The node ID
    * @access public
    * @return array         An array of the child node objects
    * @see _getChildren()
    */
    function getChildren($id) {
        $this->_debugMessage("getChildren($id)");
        return $this->_getNodes(NESE_FETCH_CHILDREN, $id);
    }
    
    /**
    * Fetch _all_ children (the branch) of a node given by id including the node itself
    *
    * Another big difference to getChildren() is that no level information
    * or other meta data is queried.
    * You only get a plain array of the node object below the given nodes.
    *
    * @param        string  $id     The node ID
    * @access public
    * @return array         An array of the child node objects
    * @see _getSubBranch()
    */
    function getSubBranch($id) {
        $this->_debugMessage("getSubBranch($id)");
        return $this->_getNodes(NESE_FETCH_SUBBRANCH, $id);
    }
    
    
    
    /**
    * Fetch the data of a node with the given id
    *
    * @param     integer    $id        The node id of the node we want to fetch
    * @access    public
    * @see       _getNodes()
    * @return object  NestedSet_Node   The nodes data as object
    */
    function pickNode($id) {
        $this->_debugMessage("pickNode($id)");
        return $this->_getNodes(NESE_FETCH_NODEBYID, $id);
    }
    
    // +----------------------------------------------+
    // | NestedSet manipulation and query methods     |
    // |----------------------------------------------+
    // | Querying the tree                            |
    // +----------------------------------------------+
    // | [PRIVATE]                                    |
    // +----------------------------------------------+
    
    /**
    * Wrapper to get all information available from a tree
    *
    * Depending on $type a query is prepared and  executed by calling other private methods.
    * The return value is an array with node objects.
    * Don't call this directly - there are public methods which do this job
    *
    * Possible values for type:
    *
    * [Fetch the whole tree(s)]
    * NESE_FETCH_ALLNODES
    *
    * [Fetch a node by a given ID]
    * NESE_FETCH_ROOTNODES
    *
    * [Fetch parents of a node]
    * NESE_FETCH_PARENTS
    *
    * [Fetch _immediate_ children of a node]
    * NESE_FETCH_CHILDREN
    *
    * [Fetch _all_ children of a node]
    * NESE_FETCH_SUBBRANCH
    *
    * [Fetch all nodes within the same level and tree]
    * NESE_FETCH_SISTERS
    *
    * [Fetch the whole branch where a given node id is in]
    * NESE_FETCH_BRANCH
    *
    * @param     intefer    $type     Which information do we want
    * @param     integer    $id        Used to get a node, parents, children, branch belonging to a node id
    * @see        _dogetAllNodes()
    * @see        _dogetRootNodes()
    * @see        _dopickNode()
    * @see        _dogetBranch()
    * @see        _dogetParents()
    * @see        _dogetChildren()
    * @return    mixed    Array with node objects or a single node object
    * @access    private
    */
    function _getNodes($type = NESE_FETCH_ALLNODES, $id = false) {
        
        $this->nodes = array();
        switch ($type) {
            
            case NESE_FETCH_ALLNODES:
            $this->_debugMessage("_getNodes($id): NESE_FETCH_ALLNODES");
            
            $res = $this->_dogetAllNodes();
            
            break;
            
            case NESE_FETCH_ROOTNODES:
            $this->_debugMessage("_getNodes($id): NESE_FETCH_ROOTNODES");
            
            $res = $this->_dogetRootNodes();
            
            break;
            
            case NESE_FETCH_NODEBYID:
            $this->_debugMessage("_getNodes($id):NESE_FETCH_NODEBYID");
            
            $res = $this->_dopickNode($id);
            
            break;
            
            case NESE_FETCH_BRANCH:
            $this->_debugMessage("_getNodes($id):NESE_FETCH_BRANCH");
            $res = $this->_dogetBranch($id);
            break;
            
            case NESE_FETCH_PARENTS:
            $this->_debugMessage("_getNodes($id):NESE_FETCH_PARENTS");
            
            $res = $this->_dogetParents($id);
            
            break;
            
            case NESE_FETCH_CHILDREN:
            $this->_debugMessage("_getNodes($id):NESE_FETCH_CHILDREN");
            
            $res = $this->_dogetChildren($id);
            
            break;
            
            case NESE_FETCH_SUBBRANCH:
            $this->_debugMessage("_getNodes($id):NESE_FETCH_SUBBRANCH");
            
            $res = $this->_dogetSubBranch($id);
            
            break;
            
        }
        
        if(!$res) {
            return false;
        }
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        
        
        for($i=0;$i<count($res);$i++) {
            
            $row = $res[$i];
            $node_id = $row["id"];
            // Create an instance of the node container
            $nodes[$node_id] = new NestedSet_Node($row);
            
            // EVENT (nodeLoad)
            $this->triggerEvent('nodeLoad', $nodes[$node_id]);
        }
        
        if ($type == NESE_FETCH_NODEBYID) {
            // Das Knoten Objekt mit $id zurückliefern
            return $nodes[$node_id];
        } else {
            // Das Array mit allen gefundenen Knoten zurückliefern
            return $nodes;
        }
    }
    
    
    // The following private methods are provding the queries
    // for specific tree informations
    
    /**
    * @access    private
    */
    function _dogetBranch($id) {
        $this->_debugMessage("_dogetBranch($id)");
        $tb = $this->node_table;
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        
        if(!is_object($id) || !$id->id) {
            $thisnode = $this->pickNode($id);
        }
        else {
            $thisnode = $id;
        }
        
        $rootid = $thisnode->rootid;
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        
        $sql = "SELECT $sel FROM $tb WHERE $froot='$rootid' ORDER by $flevel, $freh ASC";
        
        return $this->db->getAll($sql);;
    }

    /**
    * @access    private
    */    
    function _dopickNode($id) {
        $this->_debugMessage("_dopickNode($id)");
        
        if(is_object($id) && $id->id) {
            $id = $id->id;
        }
        
        
        $tb = $this->node_table;
        
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        
        $sql = "SELECT $sel FROM $tb WHERE $fid='$id'";
        return $this->db->getAll($sql);;
    }
    
    /**
    * @access    private
    */    
    function _dogetAllNodes() {
        $this->_debugMessage("_dogetAllNodes()");
        $tb = $this->node_table;
        
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        $sql = "SELECT $sel FROM $tb ORDER by $flevel, $freh ASC";
        return $this->db->getAll($sql);;
    }

    /**
    * @access    private
    */    
    function _dogetRootNodes() {
        $this->_debugMessage("_dogetRootNodes()");
        $tb = $this->node_table;
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        $sql = "SELECT $sel FROM $tb WHERE $fid = $froot ORDER BY $freh ASC";
        return $this->db->getAll($sql);;
    }

    /**
    * @access    private
    */    
    function _dogetParents($id) {
        $this->_debugMessage("_dogetParents($id)");
        $tb = $this->node_table;
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $flevel = $this->flparams["level"];
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        
        if(!is_object($id) || !$id->id) {
            $child = $this->pickNode($id);
        }
        else {
            $child = $id;
        }
        $clevel = $child->level;
        $crootid = $child->rootid;
        $cl = $child->l;
        $sql = "
        SELECT $sel FROM $tb WHERE $froot = $crootid AND $flevel < $clevel AND $flft < $cl ORDER by $flevel $freh ASC";
        return $this->db->getAll($sql);;
    }

    /**
    * @access    private
    */    
    function _dogetChildren($id) {
        
        $this->_debugMessage("_dogetChildren($id)");
        $tb = $this->node_table;
        $froot = $this->flparams["rootid"];
        $freh = $this->flparams["norder"];
        $fid = $this->flparams["id"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $flevel = $this->flparams["level"];
        
        if(!is_object($id) || !$id->id) {
            $parent = $this->pickNode($id);
        }
        else {
            $parent = $id;
        }
        $lft = $parent->l;
        $rgt = $parent->r;
        
        if($lft == $rgt-1) {
            return false;
        }
        
        $plevel = $parent->level;
        $rootid = $parent->rootid;
        
        
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        $sql = "
        SELECT $sel FROM $tb where $froot='$rootid' AND $flevel = $plevel+1 AND $flft BETWEEN $lft AND $rgt ORDER by $freh ASC";
        return $this->db->getAll($sql);;
    }

    /**
    * @access    private
    */    
    function _dogetSubBranch($id)
    {
        $this->_debugMessage("_dogetSubBranch($id)");
        $tb = $this->node_table;
        $froot = $this->flparams["rootid"];
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $fid = $this->flparams["id"];
        
        
        reset($this->params);
        foreach($this->params AS $key => $val) {
            $queryfields[] = "$key AS $val";
        }
        $sel = implode(", ", $queryfields);
        
        if(!is_object($id) || !$id->id) {
            $parent = $this->pickNode($id);
        }
        else {
            $parent = $id;
        }
        
        $lft = $parent->l;
        $rgt = $parent->r;
        $rootid = $parent->rootid;
        
        $sel = implode(", ", $queryfields);
        $sql = "SELECT $sel FROM $tb WHERE $flft between $lft and $rgt and $froot='$rootid' and $fid != $id";
        
        return $this->db->getAll($sql);;
    }
    
    
    
    
    // +----------------------------------------------+
    // | NestedSet manipulation and query methods     |
    // |----------------------------------------------+
    // | insert / delete / update of nodes            |
    // +----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    
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
    * @param     array    $values      Hash with param => value pairs of the node (see $this->params)
    * @param     integer  $id          ID of target node (the rootnode after which the node should be inserted)
    * @param     bool     $first       Danger: Deletes and (re)init's the hole tree - sequences are reset
    * @access    public
    */
    function createRootNode($values, $id = false, $first = false) {
        $this->_debugMessage("createRootNode($values, $id = false, $first = false)");
        
        // Try to aquire a table lock
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        $tb = $this->node_table;
        $addval = array();
        $addval[$flevel] = 1;
        // Shall we delete the existing tree (reinit)
        if ($first) {
            $sql = "DELETE FROM $tb";
            $this->db->query($sql);
            $this->db->dropSequence($tb."_".$fid);
            // New order of the new node will be 1
            $addval[$freh] = 1;
        } else {
            
            // Let's open a gap for the new node
            $parent = $this->pickNode($id);
            $parent_order = $parent->norder;
            $addval[$freh] = $parent_order + 1;
        }
        
        // Sequence of node id (equals to root id in this case
        $addval[$froot] = $node_id = $addval[$fid] = $this->db->nextId($tb."_".$fid);
        
        // Left/Right values for rootnodes
        $addval[$flft] = "1";
        $addval[$frgt] = "2";
        
        
        // Transform the node data hash to a query
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        
        if(!$first)
        {
            // Open the gap
            $sql = "UPDATE $tb SET $freh=$freh+1 WHERE $fid=$froot AND $freh > $parent_order";
            $res = $this->db->query($sql);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        // Insert the new node
        $sql = "INSERT INTO $tb SET $qr";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        // EVENT (nodeCreate)
        $thisnode = &$this->pickNode($node_id);
        $this->triggerEvent('nodeCreate', $thisnode);
        return $node_id;
    }
    
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
    * @param     integer    $id          Parent node ID
    * @param     array      $values      Hash with param => value pairs of the node (see $this->params)
    * @return    integer    $node_id     ID of the new node
    * @access    public
    */
    function createSubNode($id, $values) {
        
        $this->_debugMessage("createSubNode($id, $values)");
        
        // Try to aquire a table lock
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        
        // Get the children of the target node
        $children = $this->getChildren($id);
        
        // We have children here
        if ($children) {
            
            // Get the last child
            $last = array_pop($children);
            
            // What we have to do is virtually an insert of a node after the last child
            // So we don't have to proceed creating a subnode
            return $this->createRightNode($last->id, $values);
        }
        
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $froot = $this->flparams["rootid"];
        $fid = $this->flparams["id"];
        $thisnode = $this->pickNode($id);
        $lft = $thisnode->l;
        $rgt = $thisnode->r;
        $rootid = $thisnode->rootid;
        $plevel = $thisnode->level;
        $tb = $this->node_table;
        
        // Open the gap
        $sql = "UPDATE $tb SET $flft= $flft+2 WHERE $froot = '$rootid' AND $flft >  '$rgt' AND $frgt >= '$rgt'";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        $sql = "UPDATE $tb SET $frgt =  $frgt+2 WHERE $froot = '$rootid' AND $frgt >=  '$rgt'";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        $addval = array();
        $addval[$flft] = $rgt;
        $addval[$frgt] = $rgt + 1;
        $addval[$froot] = $rootid;
        $addval[$freh] = 1;
        $addval[$flevel] = $plevel + 1;
        $node_id = $addval[$fid] = $this->db->nextId($tb."_".$fid);
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        $sql = "INSERT INTO $tb SET $qr";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        // EVENT (NodeCreate)
        $thisnode = $this->pickNode($node_id);
        $this->triggerEvent('nodeCreate', $thisnode);
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
    * @param     int   $target        Target node ID
    * @param     array      $values      Hash with param => value pairs of the node (see $this->params)
    * @return    integer    $node_id     ID of the new node
    * @access    public
    */
    function createRightNode($target, $values) {
        
        $this->_debugMessage("createRightNode($target, $values)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        $id = $target;
        
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $froot = $this->flparams["rootid"];
        $freh = $this->flparams["norder"];
        $fid = $this->flparams["id"];
        $flevel = $this->flparams["level"];
        
        // Get the target node
        $thisnode = $this->pickNode($id);
        
        // If the target node is a rootnode we virtually want to create a new root node
        if ($thisnode->rootid == $thisnode->id) {
            return $this->createRootNode($values, $id);
        }
        
        $lft = $thisnode->l;
        $rgt = $thisnode->r;
        $rootid = $thisnode->rootid;
        $level = $thisnode->level;
        $parent_order = $thisnode->norder;
        
        
        
        $tb = $this->node_table;
        $addval = array();
        
        $parents = $this->getParents($id);
        $parent = array_pop($parents);
        $plft = $parent->l;
        $prgt = $parent->r;
        
        
        // Open the gap within the current level
        $sql = "UPDATE $tb SET $freh=$freh+1 WHERE $froot='$rootid' AND $flft > $lft AND $flevel=$level AND $flft between $plft and $prgt";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        // Update all nodes which have dependent left and right values
        $sql = "
        UPDATE $tb SET
        $flft =  if($flft>$rgt, $flft+2, $flft),
        $frgt =  if($frgt>$rgt, $frgt+2, $frgt)
        WHERE $froot = '$rootid' AND $frgt >  '$rgt'";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        $addval[$freh] = $parent_order + 1;
        $addval[$flft] = $rgt + 1;
        $addval[$frgt] = $rgt + 2;
        $addval[$froot] = $rootid;
        $addval[$flevel] = $level;
        $node_id = $addval[$fid] = $this->db->nextId($tb."_".$fid);
        
        if (!$qr = $this->_values2Query($values, $addval)) {
            return false;
        }
        
        // Insert the new node
        $sql = "INSERT INTO $tb SET $qr";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        // EVENT (NodeCreate)
        $thisnode = & $this->pickNode($node_id);
        $this->triggerEvent('nodeCreate', $thisnode);
        return $thisnode;
    }
    
    /**
    * Deletes a node
    *
    * @param     integer    $id            ID of the node to be deleted
    * @access    public
    */
    function deleteNode($id) {
        $this->_debugMessage("deleteNode($id)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        $thisnode = $this->pickNode($id);
        
        // EVENT (NodeDelete)
        $this->triggerEvent('nodeDelete', $thisnode);
        
        
        $tb = $this->node_table;
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $fid = $this->flparams["id"];
        $froot = $this->flparams["rootid"];
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        
        
        
        if (!$thisnode) {
            return false;
        }
        $lft = $thisnode->l;
        $rgt = $thisnode->r;
        $order = $thisnode->norder;
        $level = $thisnode->level;
        $rootid = $thisnode->rootid;
        $len = $rgt - $lft + 1;
        
        // Delete the node
        $sql =
        "DELETE from $tb WHERE $flft between $lft and $rgt and $froot='$rootid'";
        
        $this->db->query($sql);
        
        
        if ($thisnode->id != $thisnode->rootid) {
            // The node isn't a rootnode
            
            // Close the gap
            $sql = "
            UPDATE $tb SET
            $flft=if($flft>$lft,$flft-$len,$flft),
            $frgt=if($frgt>$lft,$frgt-$len,$frgt)
            WHERE $froot='$rootid'
            AND ($flft>$lft OR $frgt>$rgt)";
            $res = $this->db->query($sql);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
            
            // Re-order
            $sql = "UPDATE $tb SET $freh=$freh-1 WHERE $froot='$rootid' AND $flevel=$level and $freh > $order";
            $res = $this->db->query($sql);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
            
        } else {
            
            // A rootnode was deleted and we only have to close the gap inside the order
            $sql =
            "UPDATE $tb SET $freh=$freh-1 WHERE $froot=$fid AND $freh > $order";
            $res = $this->db->query($sql);
            $this->_testFatalAbort($res, __FILE__,  __LINE__);
        }
        return true;
    }
    /**
    * Changes the payload of a node
    *
    * @param     integer    $id          Node ID
    * @param     array    $values        Hash with param => value pairs of the node (see $this->params)
    * @access    public
    */
    function updateNode($id,$values) {
        $this->_debugMessage("updateNode($id, $values)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        
        // EVENT (NodeUpdate)
        $thisnode = & $this->pickNode($id);
        $eparams = array('values' => $values);
        $this->triggerEvent('nodeUpdate', $thisnode, $eparams);
        
        $fid = $this->flparams["id"];
        $addvalues = array();
        if (!$qr = $this->_values2Query($values, $addvalues)) {
            return false;
        }
        $sql = "UPDATE $this->node_table SET $qr WHERE $fid='$id'";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__,  __LINE__);
        
        return true;
    }
    
    
    
    // +----------------------------------------------+
    // | Moving and copying                           |
    // |----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    
    /**
    * Wrapper for node moving and copying
    *
    * @param     integer    $id            Source ID
    * @param     integer    $target        Target ID
    * @param     array    $pos             Position (BEfore, AFter, SUB)
    * @param     bool         $copy                Shall we create a copy
    * @return        int      $nodeID                  ID of the moved node
    * @access    public
    * @see        _moveInsideLevel
    * @see        _moveAcross
    * @see        moveRoot2Root
    */
    function moveTree($id, $target, $pos, $copy = false) {
        $this->_debugMessage("moveTree($id, $target, $pos, $copy = false)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        // This operations don't need callbacks except the copy handler
        // which ignores this setting
        $this->skipCallbacks = true;
        // Get information about source and target
        $source = $this->pickNode($id);
        $target = $this->pickNode($target);
        
        // We have a recursion - let's stop
        if (($target->rootid == $source->rootid) && (($source->l < $target->l) && ($source->r > $target->r))) {
            
            return new PEAR_Error($this->_getMessage(NESE_ERROR_RECURSION),NESE_ERROR_RECURSION);
        }
        
        // Insert/move before or after
        if ($pos == "BE" || $pos == "AF") {
            
            if (($source->rootid == $source->id)
            && ($target->rootid == $target->id) && !$copy) {
                
                // We have to move a rootnode which is different from moving inside a tree
                return $this->moveRoot2Root($source, $target, $pos, $copy);
            }
            
            if (($source->rootid == $target->rootid) && ($source->level == $target->level)) {
                
                /*
                $parents = $this->getParents($id);
                $s_parent = array_pop($parents);
                $parents = $this->getParents($target);
                $t_parent = array_pop($parents);
                */
                // We have to move inside the same subtree and inside the same level - no big deal
                return $this->_moveInsideLevel($source, $target, $s_parent, $pos, $copy);
            }
            
        }
        
        // We have to move between different levels and maybe subtrees - let's rock ;)
        return $this->_moveAcross($source, $target, $pos, $copy);
    }
    
    /**
    * Moves nodes and trees to other subtrees or levels
    *
    * <pre>
    * [+] <--------------------------------+
    * +-[\] root1 [target]                 |
    *     <-------------------------+      |
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
    function _moveAcross($source, $target, $pos, $copy = false) {
        
        $this->_debugMessage("_moveAcross($source, $target, $pos, $copy = false)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        $tb = $this->node_table;
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $fid = $this->flparams["id"];
        $froot = $this->flparams["rootid"];
        $freh = $this->flparams["norder"];
        
        $s_id = $source->id;
        $t_id = $target->id;
        $rootid = $target->rootid;
        reset($this->params);
        
        // Get the current data from a node and exclude the id params which will be changed
        // because of the node move
        foreach($this->params AS $key => $val) {
            if ($source->$val && ($val != "id") && ($val != "rootid")
            && ($val != "l") && ($val != "r") && ($val != "norder") && ($val != "level")) {
                $values[$key] = trim($source->$val);
            }
        }
        if ($pos != "SUB") {
            $c_id = $this->createRightNode($t_id, $values);
            $clone = $this->pickNode($c_id);
            if ($pos == "BE") {
                $this->moveTree($c_id, $t_id, $pos);
            }
        } else {
            $c_id = $this->createSubNode($t_id, $values);
            
            $clone = $this->pickNode($c_id);
        }
        $relations[$s_id] = $c_id;
        $children = $this->getChildren($source);
        $first = true;
        if($children) {
            
            // Recurse trough the child nodes
            reset($children);
            foreach($children AS $key => $val) {
                if ($first) {
                    $first = false;
                    $previd =
                    $this->_r_moveAcross($val, $clone, "createSubNode", $relations);
                } else {
                    $sister = $this->pickNode($previd);
                    $previd =
                    $this->_r_moveAcross($val, $sister, "createRightNode", $relations);
                }
            }
        }
        $this->_moveCleanup($relations, $copy);
        if(!$copy) {
            return $source->id;
        } else {
            return $clone->id;
        }
    }
    /**
    * Recursion for _moveAcross
    *
    * @param     object     NodeCT $source    Source
    * @param     object     NodeCT $target    Target
    * @param     string    $action            createRightNode|createSubNode
    * @param     array    $relations        Hash $h[old ID]=new ID - maps the source node to the new created node (clone)
    * @access    private
    * @see        _moveAcross
    */
    function _r_moveAcross($source, $target, $action, &$relations) {
        $this->_debugMessage("_r_moveAcross($source, $target, $action, &$relations)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        reset($this->params);
        foreach($this->params AS $key => $val) {
            if ($source->$val && ($val != "id") && ($val != "rootid")
            && ($val != "l") && ($val != "r") && ($val != "norder") && ($val != "level")) {
                $values[$key] = trim($source->$val);
            }
        }
        $s_id = $source->id;
        $t_id = $target->id;
        $c_id = $this->$action($t_id, $values);
        $relations[$s_id] = $c_id;
        $children = $this->getChildren($source);
        if(!$children) {
            return $c_id;
        }
        
        $clone = $this->pickNode($c_id);
        $first = true;
        
        reset($children);
        foreach($children AS $key => $val) {
            if ($first) {
                $first = false;
                $previd =
                $this->_r_moveAcross($val, $clone, "createSubNode",$relations);
            } else {
                $sister = $this->pickNode($previd);
                $previd = $this->_r_moveAcross($val, $sister, "createRightNode",$relations);
            }
        }
        return $c_id;
    }
    /**
    * Deletes the old subtree (node) and writes the node id's into the cloned tree
    *
    *
    * @param     array    $relations        Hash in der Form $h[alteid]=neueid
    * @param     array    $copy                     Are we in copy mode?
    * @access    private
    */
    function _moveCleanup($relations, $copy = false) {
        $this->_debugMessage("_moveCleanup($relations, $copy = false)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        $tb = $this->node_table;
        $fid = $this->flparams["id"];
        $froot = $this->flparams["rootid"];
        
        
        reset($relations);
        foreach($relations AS $key => $val) {
            
            $clone = $this->pickNode($val);
            if ($copy) {
                // EVENT (NodeCopy)
                $thisnode = &$this->pickNode($key);
                $eparams = array('clone' => $clone);
                $this->triggerEvent('nodeCopy', $thisnode, $eparams);
                continue;
            }
            
            
            // No callbacks here because the node itself doesn't get changed
            // Only it's position
            // If one needs a callback here please let me know
            $this->skipCallbacks = true;
            $this->deleteNode($key, true);
            
            // It's isn't a rootnode
            if ($clone->id != $clone->rootid) {
                $u_values = array();
                $u_id = $val;
                $u_values[$fid] = $key;
                $this->updateNode($u_id, $u_values);
            } else {
                $sql = "UPDATE $tb SET $fid='$key',$froot='$key' WHERE $fid='$val'";
                $this->db->query($sql);
                $orootid = $clone->rootid;
                $sql = "UPDATE $tb SET $froot='$key' WHERE $froot='$orootid'";
                $this->db->query($sql);
            }
            $this->skipCallbacks = false;
        }
        return true;
    }
    /**
    * Moves a node or subtree inside the same level
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
    function _moveInsideLevel($source, $target, $parent, $pos, $copy = false) {
        
        $this->_debugMessage("_moveInsideLevel($source, $target, $parent, $pos, $copy = false)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        
        // If we only want to copy it's quite easy cause no gap will occur
        // as in move mode
        if ($copy) {
            $parents = $this->getParents($target->id);
            
            
            $ntarget = @array_pop($parents);
            if (is_object($ntarget)) {
                $npos = "SUB";
            } else {
                $npos = $pos;
                $ntarget = $target;
            }
            
            // Let's move the node to it's destination
            $nroot = $this->_moveAcross($source, $ntarget, $npos, $copy);
            
            // Change the order
            return $this->moveTree($nroot, $target->id, $pos);
        }
        
        $parents = $this->getParents($source);
        $parent = array_pop($parents);
        $plft = $parent->l;
        $prgt = $parent->r;
        
        $tb = $this->node_table;
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $fid = $this->flparams["id"];
        $froot = $this->flparams["rootid"];
        $freh = $this->flparams["norder"];
        $flevel = $this->flparams["level"];
        $s_order = $source->norder;
        $t_order = $target->norder;
        $level = $source->level;
        $rootid = $source->rootid;
        $s_id = $source->id;
        $t_id = $target->id;
        
        
        if ($s_order < $t_order) {
            if ($pos == "BE") {
                
                $sql = "UPDATE $tb
                        SET $freh=$freh-1 WHERE $freh between $s_order and $t_order 
                        AND $fid!=$t_id AND $fid!=$s_id  AND $flevel='$level' AND $flft between $plft and $prgt";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                $sql = "UPDATE $tb SET $freh=$t_order-1 WHERE $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
            elseif($pos == "AF") {
                
                $sql = "UPDATE $tb SET $freh=$freh-1 WHERE
                        $freh between $s_order and $t_order AND $fid!=$s_id  AND $flevel='$level' AND $flft between $plft and $prgt";  
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                $sql = "UPDATE $tb SET $freh=$t_order WHERE $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        
        if ($s_order > $t_order) {
            if ($pos == "BE") {
                
                $sql = "UPDATE $tb SET $freh=$freh+1 WHERE
                        $freh between $t_order AND $s_order
                        AND $fid != $s_id AND $froot='$rootid' AND $flevel='$level' AND $flft between $plft and $prgt and $froot='$rootid'";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET $freh=$t_order WHERE $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
            elseif($pos == "AF") {
                
                $sql = "UPDATE $tb SET $freh=$freh+1 WHERE $freh between $t_order and $s_order AND $fid!=$t_id AND $fid!=$s_id AND $froot='$rootid' AND $flevel='$level' AND $flft between $plft and $prgt";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET $freh=$t_order+1 WHERE $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        return $source->id;
    }
    
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
    function moveRoot2Root($source, $target, $pos, $copy) {
        
        $this->_debugMessage("moveRoot2Root($source, $target, $pos, $copy)");
        if(PEAR::isError($lock=$this->_setLock())) {
            return $lock;
        }
        $tb = $this->node_table;
        $flft = $this->flparams["l"];
        $frgt = $this->flparams["r"];
        $fid = $this->flparams["id"];
        $froot = $this->flparams["rootid"];
        $freh = $this->flparams["norder"];
        $s_order = $source->norder;
        $t_order = $target->norder;
        $s_id = $source->id;
        $t_id = $target->id;
        
        if ($s_order < $t_order) {
            if ($pos == "BE") {
                
                $sql = "UPDATE $tb SET
                $freh=$freh-1
                WHERE
                $freh between $s_order and $t_order
                AND $fid!=$t_id AND $fid!=$s_id
                AND $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                $sql = "UPDATE $tb SET
                $freh=$t_order -1
                WHERE
                $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
            elseif($pos == "AF") {
                
                $sql = "UPDATE $tb SET
                $freh=$freh-1
                WHERE
                $freh between $s_order and $t_order
                AND $fid!=$s_id
                AND $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET
                $freh=$t_order
                WHERE
                $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        
        if ($s_order > $t_order) {
            if ($pos == "BE") {
                
                $sql = "UPDATE $tb SET
                $freh=$freh+1
                WHERE
                $freh between $t_order AND $s_order
                AND $fid != $s_id
                AND $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                
                $sql = "UPDATE $tb SET
                $freh=$t_order
                WHERE
                $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
            }
            elseif($pos == "AF") {
                
                $sql = "UPDATE $tb SET
                $freh=$freh+1
                WHERE
                $freh between $t_order and $s_order
                AND $fid!=$t_id AND $fid!=$s_id AND $froot=$fid";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
                
                $sql = "UPDATE $tb SET
                $freh=$t_order+1
                WHERE
                $fid = $s_id";
                $res = $this->db->query($sql);
                $this->_testFatalAbort($res, __FILE__, __LINE__);
            }
        }
        
        return $source->id;
    }
    
    
    // +-----------------------+
    // | Helper methods        |
    // +-----------------------+
    
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
        
        $this->_debugMessage("_testFatalAbort($errobj, $file, $line)");
        
        if ($this->debug) {
            $message = $errobj->getUserInfo();
            $code = $errobj->getCode();
            $msg = $message." ($code) "."in file $file at line $line";
        } else {
            $msg = $errobj->getMessage();
            $code = $errobj->getCode();
        }
        
        $this->raiseError($msg, $code, PEAR_ERROR_TRIGGER, E_USER_ERROR);
    }
    
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
        
        $this->eventListeners[$event][$listenerID] = &$listener;
        return $listenerID;
    }
    
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
        return true;
    }
    
    /**
    * Triggers and event an calls the event listeners
    *
    * @param        string $event   The Event that occured
    * @param        object node $node A Reference to the node object which was subject to changes
    * @param        array $eparams  A associative array of params which may be needed by the handler
    * @return   bool
    * @access public
    */
    function triggerEvent($event, &$node, $eparams=false) {
        
        if($this->skipCallbacks || !is_array($this->eventListeners) || count($this->eventListeners) == 0) {
            return false;
        }
        reset($this->eventListeners[$event]);
        foreach($this->eventListeners[$event] AS $key=>$val) {
            
            if(!method_exists($val, 'callEvent')) {
                return new PEAR_Error($this->_getMessage(NESE_ERROR_NOHANDLER), NESE_ERROR_NOHANDLER);
            }
            $val->callEvent($event, $node, $eparams);
        }
        return true;
    }
    
    /**
    * Sets an object attribute
    *
    * @param        array $attr     An associative array with attributes
    *
    * @return   bool
    * @access public
    */
    function setAttr($attr) {
        
        if(!is_array($attr) || count($attr) == 0) {
            return false;
        }
        
        reset($attr);
        foreach($attr AS $key=>$val)
        {
            $this->$key = $val;
        }
        return true;
    }
    
    /**
    * Tests if a database lock is set
    *
    * @access public
    */
    function testLock()
    {
        $this->_debugMessage("testLock()");
        
        if($lockID = $this->structureTableLock) {
            return $lockID;
        }
        
        $this->_lockGC();
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $stamp = Time();
        $lockTTL = $stamp - $this->lockTTL;
        $sql = "SELECT lockID FROM $tb WHERE lockTable='$stb'";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        
        if($res->numRows()) {
            return new PEAR_Error($this->_getMessage(NESE_ERROR_TBLOCKED),NESE_ERROR_TBLOCKED);
        }
        return false;
    }

    /**
    * @access private
    */    
    function _setLock()
    {
        $lock = $this->testLock();
        if(PEAR::isError($lock)) {
            return $lock;
        }
        
        $this->_debugMessage("_setLock()");
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $stamp = Time();
        if(!$lockID = $this->structureTableLock) {
            $lockID = $this->structureTableLock = uniqid("lck-");
            $sql = "INSERT INTO $tb SET lockID='$lockID', lockTable='$stb', lockStamp='$stamp'";
        }
        else
        {
            $sql = "UPDATE $tb SET lockStamp='$stamp' WHERE lockID='$lockID' and lockTable='$stb'";
        }
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        
        return $lockID;
    }
    
    /**
    * @access private
    */     
    function _releaseLock()
    {
        $this->_debugMessage("_releaseLock()");
        if(!$lockID = $this->structureTableLock) {
            return false;
        }
        
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $sql = "DELETE FROM $tb WHERE lockTable='$stb' AND lockID = '$lockID'";
        $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
        return true;
    }
    
    /**
    * @access private
    */     
    function _lockGC()
    {
        $this->_debugMessage("_lockGC()");
        $tb = $this->lock_table;
        $stb = $this->node_table;
        $stamp = Time();
        $lockTTL = $stamp - $this->lockTTL;
        $sql = "DELETE FROM $tb WHERE lockTable='$stb' AND lockStamp < $lockTTL";
        $res = $this->db->query($sql);
        $this->_testFatalAbort($res, __FILE__, __LINE__);
    }

    /**
    * @access private
    */     
    function _values2Query($values,
    $addval = false) {
        $this->_debugMessage("_values2Query($values, $addval = false)");
        if (is_array($addval)) {
            $values = $values + $addval;
        }
        
        $arq = array();
        reset($values);
        foreach($values AS $key => $val) {
            $k = trim($key);
            $v = trim($val);
            if ($k) {
                $arq[] = "$k='$v'";
            }
        }
        if (!is_array($arq) || count($arq) == 0) {
            return false;
        }
        $query = implode(", ", $arq);
        return $query;
    }

    /**
    * @access private
    */     
    function _debugMessage($msg) {
        
        if ($this->debug) {
            $time = $this->_getmicrotime();
            echo "$time::Debug:: $msg<br>\n";
        }
    }

    /**
    * @access private
    */         
    function _getMessage($code) {
        $this->_debugMessage("_getMessage($code)");
        if ($this->messages[$code]) {
            return $this->messages[$code];
        } else {
            return $this->messages[NESE_MESSAGE_UNKNOWN];
        }
    }

    /**
    * @access private
    */         
    function _getmicrotime(){
        list($usec, $sec) = explode(" ",microtime());
        return ((float)$usec + (float)$sec);
    }
    
}

/**
* Generic class for node objects
*
* @autor Daniel Khan <dk@webcluster.at>;
* @version $Revision$
* @package NestedSet
* 
* @access private
*/
Class NestedSet_Node extends PEAR {
    /**
    * Constructor
    *
    */
    function NestedSet_Node($data) {
        if (!is_array($data) || count($data) == 0) {
            return new PEAR_ERROR($errstr, NESE_ERROR_PARAM_MISSING);
        }
        $this->setAttr($data);
        return true;
    }
    function setAttr($data) {
        
        if(!is_array($data) || count($data) == 0) {
            return false;
        }
        reset($data);
        foreach($data AS $key => $val) {
            $this->$key = $val;
        }
    }
}
?>
