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
	
	function test_pickNode() {
		$nids = $this->_setupRootnodes(5);
		
		for($i=0; $i<count($nids); $i++) {
			$nid = $nids[$i];
			
			$nname = 'Node '.$nid;
			$norder = $nid;
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
	}
	
	function test_createRootNode($dist = false) {
		
		return $this->_createRootNodes(15, $dist);
	}
	
	
	function test_createRootNode__mixup() {
		return $this->test_createRootNode(true);
	}
	
	function test_getRootNodes() {
		
		$rootnodes_exp = $this->_createRootNodes(15);
		$rootnodes = $this->_NeSe->getRootNodes(true);
		$this->assertEquals($rootnodes_exp, $rootnodes, 'getRootNodes() failed');
		
		$rootnodes_exp = $this->_createRootNodes(15, true);
		$rootnodes = $this->_NeSe->getRootNodes(true);
		$this->assertEquals($rootnodes_exp, $rootnodes, 'getRootNodes() failed on mixed set');
	}
	
	
	function test_createSubNode() {
		
		$rnc = 3;
		$depth = 3;
		$npl = 3;
		return $this->_createSubNodes($rnc, $depth, $npl);
	}
	
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
		
		$this->_createSubNodes($rnc, $depth, $npl);
		$rootnodes = $this->_NeSe->getRootNodes(true);
		
		$exp_cct = 0;
		$cct = 0;
		foreach($rootnodes AS $rid=>$rootnode) {
			$cct = $cct + $this->_traverseChildren($rootnode);
			
			// Calc the expected number of children from lft-rgt
			$exp_cct = $exp_cct + floor(($rootnode['r'] - $rootnode['l'])/2);
		}
		
		// Test if all created nodes got returned
		$this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');
	}
	
	
	function test_getAllNodes() {
		$rnc = 3;
		$depth = 2;
		$npl = 3;
		
		$this->_createSubNodes($rnc, $depth, $npl);
		
		$allnodes = $this->_NeSe->getAllNodes(true);
		$rootnodes = $this->_NeSe->getRootNodes(true);
		foreach($rootnodes AS $rid=>$rootnode) {
			
			//print_r($this->_NeSe->getBranch($rid, true));
			//$cct = $cct + $this->_traverseChildren($rootnode);
			
			// Calc the expected number of children from lft-rgt
			//$exp_cct = $exp_cct + floor(($rootnode['r'] - $rootnode['l'])/2);
		}
	}
	
	
	
	function test_createSubNode__random() {
		
		$rootnodes = $this->_createRootNodes(2);
		// Number of nodes to create
		$nbr = 500;
		$available_parents = array();
		
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
			$cct = $cct + $this->_traverseChildren($rn);
			
			// Calc the expected number of children from lft-rgt
			$exp_cct = $exp_cct + floor(($rn['r'] - $rn['l'])/2);
		}
		// Test if all created nodes got returned
		$this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');
	}
	
	
	// Helpers
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
		
		foreach ($rootnodes as $id=>$parent) {
			$this->_recursCreateSubNode($id, $npl, $parent['name'],  1, $depth);
		}
		return true;
	}
	
	function _recursCreateSubNode($pid, $npl, $pname, $currdepth, $maxdepth) {
		
		if($currdepth > $maxdepth) {
			return true;
		}
		
		$newdepth = $currdepth + 1;
		for($i=0; $i<$npl; $i++) {
			
			$nindex = $i+1;
			$values = array();
			$values['STRNA'] = $pname.'.'.$nindex;
			
			$npid = $this->_NeSe->createSubNode($pid, $values);
			
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
	}
	
	function _traverseChildren($current_node, $reset=true) {
		
		static $occvals;
		
		if($reset || !isset($occvals)) {
			$occvals = array();
		}
		
		$level = $current_node['level'];
		
		$children = $this->_NeSe->getChildren($current_node['id'], true);
		
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
				$lcct = $lcct + $this->_traverseChildren($child, false);
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
}
?>