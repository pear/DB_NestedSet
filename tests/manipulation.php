<?php
require_once 'DB/NestedSet/TestCase.php';
/**
 * UnitTest
 * Manipulation method tests
 *
 * @author       Daniel Khan <dk@webcluster.at>
 * @package      DB_NestedSetTest
 * @version      $Revision$
 * @access       public
 */

class DB_NestedSet_ManipulationTest extends DB_NestedSet_TestCase {

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

    foreach($rootnodes as $rid=>$rootnode) {

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
    foreach($rootnodes as $rid=>$node) {
      $values['STRNA'] = 'U' . $x;
      //$values['ROOTID'] = -100;
      $this->_NeSe->updateNode($rid, $values);
      $rn = $this->_NeSe->pickNode($rid, true);
      $this->assertEquals('U' . $x, $rn['name'], 'Nodename update failed');
      $this->assertEquals($node['rootid'], $rn['rootid'], 'Rootid was overwritten');
      $x ++;
    }
    return true;
  }

  function test_rootUnderRoot() {
    $rootnodes = $this->_createRootNodes(3);

    $ret = $this->_NeSe->moveTree($rootnodes[1]['id'], $rootnodes[2]['id'], NESE_MOVE_BELOW);

    $source = $this->_NeSe->pickNode($rootnodes[1]['id'], true);
    $parent = $this->_NeSe->getParent($rootnodes[1]['id'], true, true, array(), false);
    $target = $this->_NeSe->pickNode($rootnodes[2]['id'], true);
    $this->assertEquals($target['id'], $source['parent'], 'Parent id from column is wrong');
    $this->assertEquals($target['id'], $parent['id'], 'Calculated parent id is wrong');
    return true;
  }

  function test_moveTree() {

    // $movemodes[] = NESE_MOVE_BEFORE;
    $movemodes[] = NESE_MOVE_AFTER;
    $movemodes[] = NESE_MOVE_BELOW;
    for($j = 0; $j < count($movemodes); $j ++) {

      $mvt = $movemodes[$j];

      // Build a nice random tree
      $rnc = 2;
      $depth = 3;
      $npl = 2;
      $relationTree = $this->_createSubNode($rnc, $depth, $npl);

      $lastrid = false;
      $rootnodes = $this->_NeSe->getRootNodes(true);
      $branches = array();
      $allnodes1 = $this->_NeSe->getAllNodes(true);
      foreach($rootnodes as $rid=>$rootnode) {

        if($lastrid) {
          $this->_NeSe->moveTree($rid, $lastrid, $mvt);
        }

        $branch = $this->_NeSe->getBranch($rid, true);
        if(! empty($branch)) {
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
      $this->assertEquals(0, count(array_diff(array_keys($allnodes1), array_keys($allnodes2))), 'Nodes got lost during the move');
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
    $this->assertFalse($root2_copy == $root2, 'Copy returned wrong node id');

    $nroot2_copy = $this->_NeSe->pickNode($root2_copy, true);
    $this->assertEquals($root2_copy, $nroot2_copy['id'], 'Copy created wrong node array');

    // Copy another Rootnode
    $root2_copy = $this->_NeSe->moveTree($root2, $root1, NESE_MOVE_AFTER, true);
    $this->assertFalse($root2_copy == $root2, 'Copy returned wrong node id');

    $nroot2_copy = $this->_NeSe->pickNode($root2_copy, true);
    $this->assertEquals($root2_copy, $nroot2_copy['id'], 'Copy created wrong node array');

    // Copy tree below another Rootnode
    $root2_copy = $this->_NeSe->moveTree($root2, $root1, NESE_MOVE_BELOW, true);
    $this->assertFalse($root2_copy == $root2, 'Copy returned wrong node id');

    $nroot2_copy = $this->_NeSe->pickNode($root2_copy, true);
    $this->assertEquals($root2_copy, $nroot2_copy['id'], 'Copy created wrong node array');

    // Copy subtree below another Rootnode
    $sub3_1_copy = $this->_NeSe->moveTree($sub3_1, $root1, NESE_MOVE_BELOW, true);
    $this->assertFalse($sub3_1_copy == $sub3_1, 'Copy returned wrong node id');

    $nsub3_1_copy = $this->_NeSe->pickNode($sub3_1_copy, true);
    $this->assertEquals($sub3_1_copy, $nsub3_1_copy['id'], 'Copy created wrong node array');
  }

  /*
   * Couldn' reproduce this
   *
  */
  function test_13166_wrong_child_order() {
    // $this->_NeSe->setSortMode(NESE_SORT_PREORDER);

    $values = array();
    $values['STRNA'] = 'root';
    $root = $this->_NeSe->createRootnode($values);
    $values['STRNA'] = 'node_1';
    $node_1 = $this->_NeSe->createSubNode($root, $values);
    $values['STRNA'] = 'node_2';
    $node_2 = $this->_NeSe->createSubNode($root, $values);
    $values['STRNA'] = 'node_3';
    $node_3 = $this->_NeSe->createSubNode($root, $values);
    $tree = $this->_NeSe->getAllNodes(true);

    $this->_indentTree($tree);

    $this->_NeSe->deleteNode($node_2);
    $tree = $this->_NeSe->getAllNodes(true);
    $this->_indentTree($tree);
    $values['STRNA'] = 'node_2';
    $node_2 = $this->_NeSe->createLeftNode($node_3, $values);
    $values['STRNA'] = 'node_22';
    $node_22 = $this->_NeSe->createLeftNode($node_2, $values);

    $tree = $this->_NeSe->getAllNodes(true);

    $this->_indentTree($tree);
  }



  function test_12341_rootnode_leftright_bug() {
    $this->_NeSe->setSortMode(NESE_SORT_PREORDER);
    $values = array();
    $values['STRNA'] = 'root_1';
    $root_1 = $this->_NeSe->createRootnode($values);
    $values['STRNA'] = 'root_2';
    $root_2 = $this->_NeSe->createRootnode($values);
    $tree = $this->_NeSe->getAllNodes(true);
    $this->_indentTree($tree);
    $values['STRNA'] = 'sub_root_1';
    $sub_root_1 = $this->_NeSe->createSubNode($root_1, $values);
    $tree = $this->_NeSe->getAllNodes(true);
    $this->_indentTree($tree);
  }
}
?>
