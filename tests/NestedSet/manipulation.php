<?php
/**
* UnitTest
* Manipulation method tests
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSetTest
* @version      $Revision$
* @access       public
*/


class tests_NestedSet_manipulation extends DB_NestedSetTest {
    
    // +----------------------------------------------+
    // | Testing manipulation methods                 |
    // |----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    
    /**
    * tests_NestedSet_common::test_deleteNode()
    *
    * Creates a tree and recursively deletes nodes doing regression tests on
    * the remaining nodes
    *
    * @access public
    * @see _deleteNodes()
    * @return bool True on completion
    */
    function test_deleteNode() {
        
        $relationTree = $this->_createRandomNodes(3, 150);
        $rootnodes = $this->_NeSe->getRootNodes(true);
        
        foreach($rootnodes AS $rid=>$rootnode) {
            
            $this->_deleteNodes($rid, true);
            $rn = $this->_NeSe->pickNode($rid, true);
            $this->assertEquals(1, $rn['l'], 'Wrong LFT value');
            $this->assertEquals(2, $rn['r'], 'Wrong RGT value');
        }
        return true;
    }
    
    /**
    * tests_NestedSet_common::test_updateNode()
    *
    * Creates some nodes and tries to update them
    *
    * @access public
    * @see _deleteNodes()
    * @return bool True on completion
    */
    function test_updateNode() {
        $rootnodes = $this->_createRootNodes(3);
        $x = 0;
        foreach($rootnodes AS $rid=>$node) {
            $values['STRNA'] = 'U'.$x;
            //$values['ROOTID'] = -100;
            $this->_NeSe->updateNode($rid, $values);
            $rn = $this->_NeSe->pickNode($rid, true);
            $this->assertEquals('U'.$x, $rn['name'], 'Nodename update failed');
            $this->assertEquals($node['rootid'], $rn['rootid'], 'Rootid was overwritten');
            $x++;
        }
        return true;
    }
    
  
    function test_moveTree() {
        
        // $movemodes[] = NESE_MOVE_BEFORE;
        $movemodes[] = NESE_MOVE_AFTER;
        $movemodes[] = NESE_MOVE_BELOW;
        for($j=0;$j<count($movemodes);$j++) {
            
            $mvt = $movemodes[$j];
            
            // Build a nice random tree
            $rnc = 2;
            $depth = 3;
            $npl = 2;
            $relationTree = $this->_createSubNode($rnc, $depth, $npl);
            
            $lastrid = false;
            $rootnodes =  $this->_NeSe->getRootNodes(true);
            $branches = array();
            $allnodes1 = $this->_NeSe->getAllNodes(true);
            foreach($rootnodes AS $rid=>$rootnode) {
                
                if($lastrid) {
                    $this->_NeSe->moveTree($rid, $lastrid, $mvt);
                }
                
                $branch = $this->_NeSe->getBranch($rid, true);
                if(!empty($branch)) {
                    $branches[] = $branch;
                }
                
                if(count($branches) == 2) {
                    $this->_moveTree__Across($branches, $mvt, count($this->_NeSe->getAllNodes(true)));
                    $branches = array();
                }
                $lastrid = $rid;
            }
            
            $allnodes2 = $this->_NeSe->getAllNodes(true);
            // Just make sure that all the nodes are still there
            $this->assertFalse(count(array_diff(array_keys($allnodes1), array_keys($allnodes2))), 'Nodes got lost during the move');
        }
        return true;
    }
    
    function test_copyTree() {
        
        $values['STRNA'] = 'Root1';
        $root1 = $this->_NeSe->createRootnode($values, false, true);
        
        $values['STRNA'] = 'Root2';
        $root2 = $this->_NeSe->createRightNode($root1, $values);        
        $values['STRNA'] = 'Sub2-1';
        $sub2_1 = $this->_NeSe->createSubNode($root2, $values);   
        
        $values['STRNA'] = 'Root2';
        $root3 = $this->_NeSe->createRightNode($root2, $values);   
        $values['STRNA'] = 'Sub3-1';
        $sub3_1 = $this->_NeSe->createSubNode($root3, $values); 
                
        
        // Copy a Rootnode
        $root2_copy = $this->_NeSe->moveTree($root2, $root1, NESE_MOVE_BEFORE, true); 
        $this->assertFalse($root2_copy==$root2, 'Copy returned wrong node id');
        
        $nroot2_copy = $this->_NeSe->pickNode($root2_copy, true);
        $this->assertEquals($root2_copy, $nroot2_copy['id'], 'Copy created wrong node array');        
        
        // Copy another Rootnode
        $root2_copy = $this->_NeSe->moveTree($root2, $root1, NESE_MOVE_AFTER, true); 
        $this->assertFalse($root2_copy==$root2, 'Copy returned wrong node id');
        
        $nroot2_copy = $this->_NeSe->pickNode($root2_copy, true);
        $this->assertEquals($root2_copy, $nroot2_copy['id'], 'Copy created wrong node array');        
        
        // Copy tree below another Rootnode
        $root2_copy = $this->_NeSe->moveTree($root2, $root1, NESE_MOVE_BELOW, true); 
        $this->assertFalse($root2_copy==$root2, 'Copy returned wrong node id');
        
        $nroot2_copy = $this->_NeSe->pickNode($root2_copy, true);
        $this->assertEquals($root2_copy, $nroot2_copy['id'], 'Copy created wrong node array');

        // Copy subtree below another Rootnode
        $sub3_1_copy = $this->_NeSe->moveTree($sub3_1, $root1, NESE_MOVE_BELOW, true); 
        $this->assertFalse($sub3_1_copy==$sub3_1, 'Copy returned wrong node id');
        
        $nsub3_1_copy = $this->_NeSe->pickNode($sub3_1_copy, true);
        $this->assertEquals($sub3_1_copy, $nsub3_1_copy['id'], 'Copy created wrong node array');        
    }
}
?>