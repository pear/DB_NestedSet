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
        
        $movemodes[] = NESE_MOVE_BEFORE;
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
    }


}
?>