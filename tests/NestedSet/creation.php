<?php
/**
* UnitTest
* Creation method tests
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSetTest
* @version      $Revision$
* @access       public
*/


class tests_NestedSet_creation extends DB_NestedSetTest {
    
    
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
    * Recursively create a tree using createSubNode and verify the results
    *
    * @access public
    * @see _createSubNode()
    * @return array Parent/Child relationship tree
    */
    function test_createSubNode() {
     
        $rnc = 3;
        $depth = 3;
        $npl = 3;
        return $this->_createSubNode($rnc, $depth, $npl);
    }
    
    /**
    * tests_NestedSet_common::test_createRightNode()
    *
    * Create some right nodes and query some meta informations
    *
    * @access public
    * @see _createSubNode()
    * @return bool True on completion
    */
    function test_createRightNode() {
        $rnc = 6;
        $rootnodes = $this->_createRootNodes($rnc);
        $x = 0;
        foreach($rootnodes AS $rid=>$rootnode) {
            $values['STRNA'] = 'R'.$x;
            $rn1 = $this->_NeSe->createRightNode($rid, $values);
            $values['STRNA'] = 'RS'.$x;
            $sid = $this->_NeSe->createSubNode($rn1, $values);
            $values['STRNA'] = 'RSR'.$x;
            
            // Try to overwrite the ROOTID which should be set inside the method
            // $values['ROOTID'] = -100;
            $rn2 = $this->_NeSe->createRightNode($sid, $values);
            $x++;
            
            $right1 = $this->_NeSe->pickNode($rn1, true);
            $right2 = $this->_NeSe->pickNode($rn2, true);
            
            
            // Root ID has to equal ID
            $this->assertEquals($right1['rootid'], $right1['id'], "Right node has wrong root id.");
            
            // Order
            $upd_rootnode = $this->_NeSe->pickNode($rid, true);
            
            $this->assertEquals($upd_rootnode['norder']+1, $right1['norder'], "Right node has wrong order.");
            
            // Level
            $this->assertEquals(1, $right1['level'], "Right node has wrong level.");
            
            // Children
            $exp_cct = floor(($right1['r'] - $right1['l'])/2);
            $allchildren = $this->_NeSe->getSubBranch($rn1, true);
            
            // This is also a good test if l/r values are ok
            $this->assertEquals($exp_cct, count($allchildren), "Right node has wrong child count.");
            
            // Order
            $upd_subnode = $this->_NeSe->pickNode($sid, true);
            $this->assertEquals($upd_subnode['norder']+1, $right2['norder'], "Right node has wrong order.");
            
            // Level
            $this->assertEquals(2, $right2['level'], "Right node has wrong level.");
            
            // Test root id
            $this->assertEquals($right1['rootid'], $right2['rootid'], "Right node has wrong root id.");
        }
        $allnodes = $this->_NeSe->getAllNodes(true);
        $this->assertEquals($rnc*4, count($allnodes), "Wrong node count after right insertion");
        return true;
    }

    /**
    * tests_NestedSet_common::test_createLeftNode()
    *
    * Create some left nodes and query some meta informations
    *
    * @access public
    * @see _createSubNode()
    * @return bool True on completion
    */
    function test_createLeftNode() {
        $rnc = 6;
        $rootnodes = $this->_createRootNodes($rnc);
        $x = 0;
        foreach($rootnodes AS $rid=>$rootnode) {
            $values['STRNA'] = 'R'.$x;
            $rn1 = $this->_NeSe->createLeftNode($rid, $values);
            $values['STRNA'] = 'RS'.$x;
            $sid = $this->_NeSe->createSubNode($rn1, $values);
            $values['STRNA'] = 'RSR'.$x;
            
            // Try to overwrite the ROOTID which should be set inside the method
            // $values['ROOTID'] = -100;
            $rn2 = $this->_NeSe->createLeftNode($sid, $values);
            $x++;
            
            $left1 = $this->_NeSe->pickNode($rn1, true);
            $left2 = $this->_NeSe->pickNode($rn2, true);
            
            
            // Root ID has to equal ID
            $this->assertEquals($left1['rootid'], $left1['id'], "Left node has wrong root id.");
            
            // Order
            $upd_rootnode = $this->_NeSe->pickNode($rid, true);
            $this->assertEquals($upd_rootnode['norder'], $left1['norder'], "Left node has wrong order.");
            
            // Level
            $this->assertEquals(1, $left1['level'], "Left  node has wrong level.");
            
            // Children
            $exp_cct = floor(($left1['r'] - $left1['l'])/2);
            $allchildren = $this->_NeSe->getSubBranch($rn1, true);
            
            // This is also a good test if l/r values are ok
            $this->assertEquals($exp_cct, count($allchildren), "Left  node has wrong child count.");
            
            // Order
            $upd_subnode = $this->_NeSe->pickNode($sid, true);
            $this->assertEquals($upd_subnode['norder']-1, $left2['norder'], "Left  node has wrong order.");
            
            // Level
            $this->assertEquals(2, $left2['level'], "Left  node has wrong level.");
            
            // Test root id
            $this->assertEquals($left1['rootid'], $left2['rootid'], "Left  node has wrong root id.");
        }
        $allnodes = $this->_NeSe->getAllNodes(true);
        $this->assertEquals($rnc*4, count($allnodes), "Wrong node count after right insertion");
        return true;
    }    
    
    /**
    * tests_NestedSet_common::createSubNode__random()
    *
    * Create some rootnodes and randomly call createSubNode() or createRightNode()
    * on the growing tree. This creates a very random structure which
    * is intended to be a real life simulation to catch bugs not beeing
    * catched by the other tests.
    * Some basic regression tests including _traverseChildren() with a relation tree
    * are made.
    *
    * @access public
    * @see _createRandomNodes()
    * @return bool True on completion
    */
    function test_createNodes__random() {
        
        $this->_createRandomNodes(3, 150);
        return true;
    }
}
?>