<?php
/**
 * UnitTest
 * Query method tests
 *
 * @author Daniel Khan <dk@webcluster.at>
 * @package DB_NestedSetTest
 * @version $Revision$
 * @access public
 */

class tests_NestedSet_query extends DB_NestedSetTest {
    // +----------------------------------------------+
    // | Testing query methods                        |
    // |----------------------------------------------+
    // | [PUBLIC]                                     |
    // +----------------------------------------------+
    var $addSQL = array('where' => '1');

    /**
     * tests_NestedSet_common::test_getAllNodes()
     *
     * Creates some nodes and verifies the result
     *
     * @access public
     * @return bool True on completion
     */
    function test_getAllNodes() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        $this->_createSubNode($rnc, $depth, $npl);

        $allnodes = $this->_NeSe->getAllNodes(true, true, $this->addSQL);
        $rootnodes = $this->_NeSe->getRootNodes(true, true, $this->addSQL);
        $exp_cct = 0;
        foreach($rootnodes AS $rid => $rootnode) {
            $exp_cct = $exp_cct + floor(($rootnode['r'] - $rootnode['l']) / 2);
        }
        // Does it really return all nodes?
        $cct = count($allnodes);
        $exp_cct = $exp_cct + count($rootnodes);
        $this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');
        // Verify the result agains pickNode()
        foreach($allnodes AS $nid => $node) {
            $this->assertEquals($this->_NeSe->pickNode($nid, true), $node, 'Result differs from pickNode()');
        }

        return true;
    }

    /**
     * tests_NestedSet_common::test_getRootNodes()
     *
     * Create 2 sets of rootnodes (ordered and mixed) and see if the result matches
     * getRootNodes()
     *
     * @access public
     * @see _createRootNodes
     * @return bool True on completion
     */
    function test_getRootNodes() {
        // Create a simple set of rootnodes
        $rootnodes_exp = $this->_createRootNodes(15);
        $rootnodes = $this->_NeSe->getRootNodes(true, true, $this->addSQL);
        $this->assertEquals($rootnodes_exp, $rootnodes, 'getRootNodes() failed');
        // Create a mixed order set of rootnodes
        $rootnodes_exp = $this->_createRootNodes(15, true);
        $rootnodes = $this->_NeSe->getRootNodes(true, true, $this->addSQL);
        $this->assertEquals($rootnodes_exp, $rootnodes, 'getRootNodes() failed on mixed set');
        return true;
    }



    /**
     * tests_NestedSet_common::test_getParents()
     *
     * Handcraft the parent tree using the relation tree from _createSubNode()
     * and compare it against getParents()
     *
     * @access public
     * @see _traverseParentRelations
     * @return bool True on completion
     */
    function test_getParents() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        // Create a new tree
        $relationTree = $this->_createSubNode($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true, true, $this->addSQL);
        // Walk trough all nodes and compare it's relations whith the one provided
        // by the relation tree
        foreach($allnodes AS $nid => $node) {
            $parents = $this->_NeSe->getParents($nid, true, true, $this->addSQL);
            $exp_parents = array_reverse($this->_traverseParentRelations($relationTree, $nid, true), true);
            $this->assertEquals($exp_parents, $parents, 'Differs from relation traversal result.');
        }
        return true;
    }

    /**
     * tests_NestedSet_common::test_getParent()
     *
     * Build a simple tree run getParent() and compare it with the relation tree
     *
     * @access public
     * @return bool True on completion
     */
    function test_getParent() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        // Create a new tree
        $relationTree = $this->_createSubNode($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        // Walk trough all nodes and compare it's relations whith the one provided
        // by the relation tree
        foreach($allnodes AS $nid => $node) {
            $parent = $this->_NeSe->getParent($nid, true, true, $this->addSQL);
            if (!isset($relationTree[$nid]['parent'])) {
                $this->assertFalse($parent, 'A rootnode returned a parent');
                continue;
            }
            $this->assertEquals($relationTree[$nid]['parent'], $parent['id'], 'Relation tree parent doesn\'t match method return');
        }
        return true;
    }

    function test_getSiblings() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        // Create a new tree
        $relationTree = $this->_createSubNode($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true, true, $this->addSQL);
        // Walk trough all nodes and compare it's relations whith the one provided
        // by the relation tree
        foreach($allnodes AS $nid => $node) {
            if (!$children = $this->_NeSe->getChildren($nid, true, true, false, $this->addSQL)) {
                continue;
            }
            foreach($children AS $cid => $child) {
                $siblings = $this->_NeSe->getSiblings($cid, true, true, $this->addSQL);
                $this->assertEquals($children, $siblings, 'Children don\'t match getSiblings()');
            }
        }

        $rootnodes = $this->_NeSe->getRootNodes(true, true, $this->addSQL);
        $rootnode = current($rootnodes);
        $siblings_of_rootnode = $this->_NeSe->getSiblings($rootnode['id'], true, true, $this->addSQL);

        $this->assertEquals($rootnodes, $siblings_of_rootnode, 'Siblings of a rootnode should be all rootnodes');

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
        $relationTree = $this->_createSubNode($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        foreach($allnodes AS $nid => $node) {
            $children = $this->_NeSe->getChildren($nid, true);

            if (empty($children)) {
                continue;
            }
            foreach($children AS $cid => $child) {
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
     * @see _createSubNode
     * @see _traverseChildren
     * @return bool True on completion
     */
    function test_getChildren() {
        $rnc = 2;
        $depth = 2;
        $npl = 3;
        // Just see if empty nodes are recognized
        $nids = $this->_setupRootnodes(3);
        foreach($nids AS $rix => $nid) {
            $this->assertFalse($this->_NeSe->getChildren($nid, true, true, false, $this->addSQL), 'getChildren returned value for empty rootnode');
        }
        // Now build a little tree to test
        $relationTree = $this->_createSubNode($rnc, $depth, $npl);

        $rootnodes = $this->_NeSe->getRootNodes(true);
        $exp_cct = 0;
        $cct = 0;
        foreach($rootnodes AS $rid => $rootnode) {
            // Traverse the tree and verify it against the relationTree
            $cct = $cct + $this->_traverseChildren($rootnode, $relationTree, true);
            // Calc the expected number of children from lft-rgt
            $exp_cct = $exp_cct + floor(($rootnode['r'] - $rootnode['l']) / 2);
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
        $this->_createSubNode($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);
        $branch = $this->_NeSe->getBranch($npl, true, true, $this->addSQL);
        $this->assertEquals($allnodes, $branch, 'Result differs from getAllNodes()');
    }

    /**
     * tests_NestedSet_common::test_getSubBranch()
     *
     * Handcraft a sub branch using the relation tree from _createSubNode()
     * and compare it against getSubBranch()
     *
     * @access public
     * @see _traverseChildRelations
     * @return bool True on completion
     */
    function test_getSubBranch() {
        $rnc = 3;
        $depth = 2;
        $npl = 3;
        // Create a new tree
        $relationTree = $this->_createSubNode($rnc, $depth, $npl);
        $allnodes = $this->_NeSe->getAllNodes(true);

        $test_nid = false;
        foreach($relationTree AS $nid => $relations) {
            $subbranch = $this->_NeSe->getSubBranch($nid, true, true, $this->addSQL);
            if($subbranch && !$test_nid) {
                $test_nid = $nid;
            }
            $exp_subbranch = $this->_traverseChildRelations($relationTree, $nid, true, true);
            $this->assertEquals($subbranch, $exp_subbranch, 'Differs from relation traversal result.');
        }

        $this->_NeSe->setSortMode(NESE_SORT_PREORDER);
        $this->_NeSe->secondarySort = 'STRNA';
        $res1 = $this->_NeSe->getSubBranch($test_nid, true, true, $this->addSQL);
        $testnode1 = current($res1);

        $res2 = $this->_NeSe->getSubBranch($test_nid, false, true, $this->addSQL);
        $testnode2 = current($res2);

        $this->assertEquals($testnode1['id'], $testnode2->id, 'Array content differs from object');

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
        for($i = 0; $i < count($nids); $i++) {
            $nid = $nids[$i];

            $nname = 'Node ' . $nid;
            $norder = $nid;
            // Pick the current node and do the tests
            $nnode = $this->_NeSe->pickNode($nid, true, true, 'id', $this->addSQL);
            // Test Array
            $this->assertTrue(is_array($nnode), "Node $nname: No array given.");
            // Test lft/rgt
            $this->assertEquals(1, $nnode['l'], "Node $nname: Wrong LFT");
            $this->assertEquals(2, $nnode['r'], "Node $nname: Wrong RGT");
            // Test order
            $this->assertEquals($norder, $nnode['norder'], "Node $nname: Wrong order.");
            // Test Level
            $this->assertEquals(1, $nnode['level'], "Node $nname: Wrong level.");
            // Test Name
            $this->assertEquals($nname, $nnode['name'], "Node $nname: Wrong name.");
        }
        return true;
    }
}

?>