<?php
/**
* UnitTest
* Unit test interface for DB_NestedSet
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSet
* @version      $Revision$
* @access       public
*/
require_once 'UnitTest.php';
class tests_NestedSet_common extends DB_NestedSetTest {
    
    
    // +----------------------------------------------+
    // | Testing creation methods                     |
    // |----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    
    /**
    * tests_NestedSet_common::test_createRootNode()
    *
    * Simply create some rootnodes and see if this works
    *
    * @access public
    * @see _createRootNodes()
    * @return array Array of created rootnodes
    */
    function test_createRootNode($dist = false) {
        
        return $this->_createRootNodes(15);
    }
    
    /**
    * tests_NestedSet_common::test_createRootNode__mixup()
    *
    * Create some rootnodes and create another rootnodes inbetween the others to look
    * if the ordering is right afterwards
    *
    * @access public
    * @see _createRootNodes()
    * @return array Array of created rootnodes
    */
    function test_createRootNode__mixup() {
        return $this->_createRootNodes(15, true);
    }
    
    
    
    /**
    * tests_NestedSet_common::test_createSubNode()
    *
    * @param type  descr
    *
    * @access public
    * @see _createSubNodes()
    * @return array Parent/Child relationship tree
    */
    function test_createSubNode() {
        
        $rnc = 3;
        $depth = 3;
        $npl = 3;
        return $this->_createSubNodes($rnc, $depth, $npl);
    }
    
    /**
    * tests_NestedSet_common::createSubNode__random()
    *
    * Create some rootnodes and randomly call createSubNodes()
    * on the growing tree. This creates a very random structure which
    * is intended to be a real life simulation to catch bugs not beeing
    * catched by the createSubNode() tests.
    * Some basic regression tests including _traverseChildren() with a relation tree
    * are made.
    *
    * @access public
    * @see _traverseChildren()
    * @return bool True on completion
    */
    function test_createSubNode__random() {
        
        $rootnodes = $this->_createRootNodes(3);
        // Number of nodes to create
        $nbr = 500;
        $available_parents = array();
        $relationTree = array();
        foreach($rootnodes AS $rid=>$rootnode) {
            $available_parents[] = $rid;
        }
        
        for($i=0; $i<$nbr-1; $i++) {
            
            $randval = mt_rand(0, count($available_parents)-1);
            $parent = $this->_NeSe->pickNode($available_parents[$randval], true);
            $values = array();
            $nindex = $i;
            $values['STRNA'] = $parent['name'].'.'.$nindex;
            $available_parents[] = $nid = $this->_NeSe->createSubNode($parent['id'], $values);
            
            $relationTree[$nid]['parent'] = $parent['id'];
            $relationTree[$parent['id']]['children'][] = $nid;
            
            $cnode = $this->_NeSe->pickNode($nid, true);
            $exp_rootid = $parent['rootid'];
            
            // Test rootid
            $this->assertEquals($exp_rootid,  $cnode['rootid'], "Node {$cnode['name']}: Wrong root id.");
            
        }
        
        
        $exp_cct = 0;
        $cct = 0;
        // Traverse the tree and verify it using getChildren
        foreach($rootnodes AS $rid=>$rootnode) {
            
            $rn = $this->_NeSe->pickNode($rid, true);
            $cct = $cct + $this->_traverseChildren($rn, $relationTree);
            
            // Calc the expected number of children from lft-rgt
            $exp_cct = $exp_cct + floor(($rn['r'] - $rn['l'])/2);
        }
        // Test if all created nodes got returned
        $this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');
    }
    
    // +----------------------------------------------+
    // | Testimg query methods                        |
    // |----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    
    /**
    * tests_NestedSet_common::test_getAllNodes()
    *
    * @access public
    * @return bool True on completion
    */
    function test_getAllNodes() {
        
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        
        $this->_createSubNodes($rnc, $depth, $npl);
        
        $allnodes = $this->_NeSe->getAllNodes(true);
        $rootnodes = $this->_NeSe->getRootNodes(true);
        $exp_cct = 0;
        foreach($rootnodes AS $rid=>$rootnode) {
            $exp_cct = $exp_cct + floor(($rootnode['r'] - $rootnode['l'])/2);
        }
        
        // Does it really return all nodes?
        $cct = count($allnodes);
        $exp_cct = $exp_cct + count($rootnodes);
        $this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');
        
        // Verify the result agains pickNode()
        foreach($allnodes AS $nid=>$node) {
            $this->assertEquals($this->_NeSe->pickNode($nid, true), $node, 'Result differs from pickNode()');
        }
    }
    
    /**
    * tests_NestedSet_common::test_getRootNodes()
    *
    * Create 2 sets of rootnodes (ordered and mixed) and see if the result matches
    * getRootNodes()
    *
    * @access public
    * @see _createRootNodes()
    * @return bool True on completion
    */
    function test_getRootNodes() {
        
        // Create a simple set of rootnodes
        $rootnodes_exp = $this->_createRootNodes(15);
        $rootnodes = $this->_NeSe->getRootNodes(true);
        $this->assertEquals($rootnodes_exp, $rootnodes, 'getRootNodes() failed');
        
        // Create a mixed order set of rootnodes
        $rootnodes_exp = $this->_createRootNodes(15, true);
        $rootnodes = $this->_NeSe->getRootNodes(true);
        $this->assertEquals($rootnodes_exp, $rootnodes, 'getRootNodes() failed on mixed set');
        return true;
    }
    
    /**
    * tests_NestedSet_common::test_getParents()
    *
    * Handcraft the parent tree using the relation tree from _createSubNodes()
    * and compare it against getParents()
    *
    * @access public
    * @see _traverseParentRelations()
    * @return bool True on completion
    */
    function test_getParents() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        
        // Create a new tree
        $relationTree = $this->_createSubNodes($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        // Walk trough all nodes and compare it's relations whith the one provided
        // by the relation tree
        foreach($allnodes AS $nid=>$node) {
            $parents = $this->_NeSe->getParents($nid,true);
            $exp_parents = array_reverse($this->_traverseParentRelations($relationTree, $nid, true), true);
            $this->assertEquals($exp_parents, $parents, 'Differs from relation traversal result.');
        }
        return true;
    }
    
    /**
    * tests_NestedSet_common::isParent()
    *
    * Create a tree, go trogh each node, fetch all children
    * and see if isParent() returns true
    *
    * @access public
    * @return bool True on completion
    */
    function test_isParent() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        $relationTree = $this->_createSubNodes($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        foreach($allnodes AS $nid=>$node) {
            
            $children = $this->_NeSe->getChildren($nid, true);
            
            if(empty($children)) {
                continue;
            }
            foreach($children AS $cid=>$child) {
                $isParent = $this->_NeSe->isParent($node, $child);
                $this->assertEquals($relationTree[$cid]['parent'] , $nid, 'Parent from relation tree differs.');
                $this->assertTrue($isParent, 'isParent was false.');
            }
        }
        return true;
    }
    
    /**
    * tests_NestedSet_common::test_getChildren()
    *
    * Create some children
    * The dirty work is done in _traverseChildren()
    * Here we only calc if the expected number of children returned matches
    * the count of getChildren()
    *
    * @access public
    * @see _createSubNodes()
    * @see _traverseChildren()
    * @return bool True on completion
    */
    function test_getChildren() {
        
        $rnc = 2;
        $depth = 2;
        $npl = 3;
        // Just see if empty nodes are recognized
        $nids = $this->_setupRootnodes(3);
        foreach($nids AS $rix=>$nid) {
            $this->assertFalse($this->_NeSe->getChildren($nid, true), 'getChildren returned value for empty rootnode');
        }
        
        // Now build a little tree to test
        
        $relationTree = $this->_createSubNodes($rnc, $depth, $npl);
        
        $rootnodes = $this->_NeSe->getRootNodes(true);
        $exp_cct = 0;
        $cct = 0;
        foreach($rootnodes AS $rid=>$rootnode) {
            
            // Traverse the tree and verify it against the relationTree
            $cct = $cct + $this->_traverseChildren($rootnode, $relationTree, true);
            
            // Calc the expected number of children from lft-rgt
            $exp_cct = $exp_cct + floor(($rootnode['r'] - $rootnode['l'])/2);
        }
        
        // Test if all created nodes got returned
        $this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');
        return true;
    }
    
    /**
    * tests_NestedSet_common::test_getBranch()
    *
    * If we only have one branch getAllNodes() has to eual getBranch()
    *
    * @access public
    * @return bool True on completion
    */
    function test_getBranch() {
        $rnc = 1;
        $depth = 2;
        $npl = 3;
        // Create a new tree
        $this->_createSubNodes($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        $branch = 	$this->_NeSe->getBranch($npl,true);
        $this->assertEquals($allnodes, $branch, 'Result differs from getAllNodes()');
    }
    
    /**
    * tests_NestedSet_common::test_getSubBranch()
    *
    * Handcraft a sub branch using the relation tree from _createSubNodes()
    * and compare it against getSubBranch()
    *
    * @access public
    * @see _traverseChildRelations()
    * @return bool True on completion
    */
    function test_getSubBranch() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        
        // Create a new tree
        $relationTree = $this->_createSubNodes($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        foreach($relationTree AS $nid=>$relations) {
            $subbranch = $this->_NeSe->getSubBranch($nid,true);
            $exp_subbranch = $this->_traverseChildRelations($relationTree, $nid, true, true);
            $this->assertEquals($subbranch, $exp_subbranch, 'Differs from relation traversal result.');
        }
        return true;
    }
    
    /**
    * tests_NestedSet_common::test_pickNode()
    *
    * Create some rootnodes and run pickNode() on it.
    *
    * @access public
    * @return bool True on completion
    */
    function test_pickNode() {
        
        // Set some rootnodes
        $nids = $this->_setupRootnodes(5);
        
        // Loop trough the node id's of the newly created rootnodes
        for($i=0; $i<count($nids); $i++) {
            $nid = $nids[$i];
            
            $nname = 'Node '.$nid;
            $norder = $nid;
            
            // Pick the current node and do the tests
            $nnode = $this->_NeSe->pickNode($nid, true);
            
            // Test Array
            $this->assertEquals(is_array($nnode), "Node $nname: No array given.");
            
            // Test lft/rgt
            $this->assertEquals(1, $nnode['l'],  "Node $nname: Wrong LFT");
            $this->assertEquals(2, $nnode['r'],  "Node $nname: Wrong RGT");
            
            // Test order
            $this->assertEquals($norder, $nnode['norder'], "Node $nname: Wrong order.");
            
            // Test Level
            $this->assertEquals(1, $nnode['level'], "Node $nname: Wrong level.");
            
            // Test Name
            $this->assertEquals($nname, $nnode['name'], "Node $nname: Wrong name.");
        }
        return true;
    }
    
    
    // +----------------------------------------------+
    // | Internal helper methods                      |
    // |----------------------------------------------+
    // | [PRIVATE]                                    |
    // +----------------------------------------------+
    
    function _setupRootnodes($nbr) {
        $nodes = array();
        $lnid = false;
        // Create some rootnodes
        for($i=0;$i<$nbr;$i++) {
            
            $nodeIndex = $i+1;
            $values = array();
            $values['STRNA'] = 'Node '.$nodeIndex;
            
            if($i==0) {
                $nid[$i] = $this->_NeSe->createRootnode($values, false, true);
            } else {
                $nid[$i] = $this->_NeSe->createRootnode($values, $nid[$i-1]);
            }
            
            $this->assertEquals($nodeIndex, $nid[$i], 'Rootnode $nodeIndex: creation failed');
        }
        $this->assertEquals($nbr, count($nid), "RootNode creation went wrong.");
        return $nid;
    }
    
    function _createRootNodes($nbr, $dist=false) {
        // Creates 10 rootnodes
        $rplc = array();
        $nodes = $this->_setupRootnodes($nbr);
        
        $disturbidx = false;
        $disturb = false;
        $disturbSet = false;
        // Disturb the order by adding a node in the middle of the set
        if($dist) {
            $values = array();
            $values['STRNA'] = 'disturb';
            $disturbidx = count($nodes);
            $disturb = 6;
            $nodes[$disturbidx] = $this->_NeSe->createRootnode($values, $disturb);
        }
        
        for($i=0; $i<count($nodes); $i++) {
            $node[$nodes[$i]] = $this->_NeSe->pickNode($nodes[$i], true);
            
            $nodeIndex = $i+1;
            
            if(!empty($disturb) && $nodeIndex - 1  == $disturb) {
                $disturbSet = true;
            }
            
            if(!$disturbSet) {
                $exp_order = $nodeIndex;
                $exp_name  = 'Node '.$nodeIndex;
            } elseif($i == $disturbidx) {
                $exp_order = $disturb+1;
                $exp_name  = 'disturb';
            } else {
                $exp_order = $nodeIndex + 1;
                $exp_name  = 'Node '.$nodeIndex;
            }
            
            // Test Array
            $this->assertEquals(is_array($node[$nodes[$i]]), "Rootnode $nodeIndex: No array given.");
            
            // Test NodeID==RootID
            $this->assertEquals($node[$nodes[$i]]['id'], $node[$nodes[$i]]['rootid'], "Rootnode $nodeIndex: NodeID/RootID not equal.");
            
            // Test lft/rgt
            $this->assertEquals(1, $node[$nodes[$i]]['l'],  "Rootnode $nodeIndex: LFT has to be 1");
            $this->assertEquals(2, $node[$nodes[$i]]['r'],  "Rootnode $nodeIndex: RGT has to be 2");
            
            // Test order
            $this->assertEquals($exp_order, $node[$nodes[$i]]['norder'], "Rootnode $nodeIndex: Wrong order.");
            
            // Test Level
            $this->assertEquals(1, $node[$nodes[$i]]['level'], "Rootnode $nodeIndex: Wrong level.");
            
            // Test Name
            $this->assertEquals($exp_name, $node[$nodes[$i]]['name'], "Rootnode $nodeIndex: Wrong name.");
        }
        return $node;
    }
    
    function _createSubNodes($rnc, $depth, $npl) {
        
        $rootnodes = $this->_createRootNodes($rnc);
        
        $init = true;
        foreach ($rootnodes as $id=>$parent) {
            $relationTree = $this->_recursCreateSubNode($id, $npl, $parent['name'],  1, $depth, $init);
            $init = false;
        }
        return $relationTree;
    }
    
    function _recursCreateSubNode($pid, $npl, $pname, $currdepth, $maxdepth, $init=false) {
        
        static $relationTree;
        if($init) {
            $relationTree = array();
        }
        if($currdepth > $maxdepth) {
            return $relationTree;
        }
        
        $newdepth = $currdepth + 1;
        for($i=0; $i<$npl; $i++) {
            
            $nindex = $i+1;
            $values = array();
            $values['STRNA'] = $pname.'.'.$nindex;
            
            $npid = $this->_NeSe->createSubNode($pid, $values);
            $relationTree[$npid]['parent'] = $pid;
            $relationTree[$pid]['children'][] = $npid;
            // fetch just created node for validation
            $nnode = $this->_NeSe->pickNode($npid, true);
            
            // fetch parent of the new node to get lft/rgt values to verify
            $pnode = $this->_NeSe->pickNode($pid, true);
            
            $plft = $pnode['l'];
            $prgt = $pnode['r'];
            
            // Expected values
            $exp_order = $nindex;
            $exp_name = $values['STRNA'];
            $exp_level = $currdepth + 1;
            $exp_lft = $prgt - 2;
            $exp_rgt = $prgt - 1;
            $exp_rootid = $pnode['rootid'];
            
            // Test Array
            $this->assertEquals(is_array($nnode), "Node {$values['STRNA']}: No array given.");
            
            // Test rootid
            $this->assertEquals($exp_rootid, $nnode['rootid'],  "Node {$values['STRNA']}: Wrong rootid");
            
            // Test lft/rgt
            $this->assertEquals($exp_lft, $nnode['l'],  "Node {$values['STRNA']}: Wrong LFT");
            $this->assertEquals($exp_rgt, $nnode['r'],  "Node {$values['STRNA']}: Wrong RGT");
            
            // Test order
            $this->assertEquals($exp_order, $nnode['norder'], "Node {$values['STRNA']}: Wrong order.");
            
            // Test Level
            $this->assertEquals($exp_level, $nnode['level'], "Node {$values['STRNA']}: Wrong level.");
            
            // Test Name
            $this->assertEquals($exp_name, $nnode['name'], "Node {$values['STRNA']}: Wrong name.");
            
            // Create new subnode
            $this->_recursCreateSubNode($npid, $npl, $values['STRNA'], $newdepth, $maxdepth);
        }
        return $relationTree;
    }
    
    function _traverseChildren($current_node, $relationTree = array(), $reset=true) {
        
        static $occvals;
        
        if($reset || !isset($occvals)) {
            $occvals = array();
        }
        
        $level = $current_node['level'];
        
        $children = $this->_NeSe->getChildren($current_node['id'], true);
        
        if(!empty($relationTree)) {
            if(is_array($exp_children = $this->_traverseChildRelations($relationTree,$current_node['id'],false, true))) {
                if(count($exp_children) == 0) {
                    $exp_children = false;
                } else {
                    $exp_children = array_reverse($exp_children, true);
                }
            }
            $this->assertEquals($exp_children, $children, "Node {$current_node['name']}: Children don't match children from relation tree.");
        }
        
        $x = 0;
        $lcct = 0;
        
        if($children) {
            $level++;
            foreach($children AS $cid=>$child) {
                
                // Test order
                $exp_order = $x +1;
                $exp_level = $level;
                $exp_rootid = $current_node['rootid'];
                $this->assertEquals($exp_order,  $child['norder'], "Node {$current_node['name']}: Wrong order value.");
                
                // Test rootid
                $this->assertEquals($exp_rootid,  $child['rootid'], "Node {$current_node['name']}: Wrong root id.");
                
                // Test level
                $this->assertEquals($exp_level,  $child['level'], "Node {$current_node['name']}: Wrong level value.");
                $lcct = $lcct + $this->_traverseChildren($child, $relationTree, false);
                $x++;
            }
        }
        
        // Calc the expexted total number of children
        // This is a nice general check if everything's worked as it should
        $exp_cct = floor(($current_node['r'] - $current_node['l']) / 2);
        $cct = $x + $lcct;
        
        
        $this->assertEquals($exp_cct, $cct, "Node {$current_node['name']}: Wrong childcount.");
        
        // Test rgt
        $lft = $current_node['l'];
        $rgt = $current_node['r'];
        $exp_rgt = ($lft + ($cct * 2) + 1);
        $this->assertEquals($exp_rgt, $rgt, "Node {$current_node['name']}: Wrong RGT value.");
        
        
        // Test if no lft/rgt values have been used twice
        $rootid = $current_node['rootid'];
        
        $this->assertFalse(isset($occvals[$lft]),
        "Node {$current_node['name']}: Uses allready used LFT value."
        );
        
        $this->assertFalse(isset($occvals[$rgt]),
        "Node {$current_node['name']}: Uses allready used RGT value."
        );
        
        $occvals[$lft] = $lft;
        $occvals[$rgt] = $rgt;
        return $cct;
    }
    
    function _traverseParentRelations($relationTree, $nid, $init=false) {
        static $relationNodes;
        if($init) {
            $relationNodes = array();
        }
        
        if(empty($relationTree[$nid]['parent'])) {
            return $relationNodes;
        }
        $parentID = $relationTree[$nid]['parent'];
        $relationNodes[$parentID] = $this->_NeSe->pickNode($parentID, true);
        $this->_traverseParentRelations($relationTree, $parentID);
        return $relationNodes;
    }
    
    function _traverseChildRelations($relationTree, $nid, $deep = false, $init=false) {
        static $relationNodes;
        if($init) {
            $relationNodes = array();
        }
        
        if(empty($relationTree[$nid]['children'])) {
            return $relationNodes;
        }
        $children = $relationTree[$nid]['children'];
        
        for($i=0;$i<count($children);$i++) {
            $cid = $children[$i];
            $relationNodes[$cid] = $this->_NeSe->pickNode($cid, true);
            if($deep) {
                $this->_traverseChildRelations($relationTree, $cid, $deep);
            }
        }
        return $relationNodes;
    }
}
?>