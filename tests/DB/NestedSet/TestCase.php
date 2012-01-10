<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'DB/NestedSet.php';

/**
* UnitTest
* Unit test interface for DB_NestedSet
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSetTest
* @version      $Revision$
* @access       public
*/
class DB_NestedSet_TestCase extends PHPUnit_Framework_TestCase  {

    /**
     *
     * @var DB_NestedSet
     */
    protected $_NeSe = false;

    /**
     *
     * @var DB_NestedSet
     */
    protected $_NeSe2 = false;

    function setUp() {

        $params = array(
        "STRID"         =>      "id",      // "id" must exist
        "ROOTID"        =>      "rootid",  // "rootid" must exist
        "l"             =>      "l",       // "l" must exist
        "r"             =>      "r",       // "r" must exist
        "STREH"         =>      "norder",  // "order" must exist
        "LEVEL"         =>      "level",   // "level" must exist
        "STRNA"         =>      "name",     // Custom - specify as many fields you want
        "parent"        =>      "parent",     // Custom - specify as many fields you want
                "tkey"           =>      "tkey"     // Custom - specify as many fields you want
        );

        $dsn = unserialize(DB_NESTEDSET_TEST_DSN);
        if (!$dsn) {
            $this->markTestSkipped('DSN information not provided');
        }

        $nese = DB_NestedSet::factory(DB_NESTEDSET_TEST_DRIVER, $dsn, $params);
        if (PEAR::isError($this->_NeSe)) {
            $this->markTestSkipped($this->_NeSe->getMessage());
        }
        $this->_NeSe = $nese;

        $this->_NeSe->setAttr(array
        (
        'node_table' => 'tb_nodes',
        'lock_table' => 'tb_locks',
        'lockTTL'    => 5,
        'debug' => 0)
        );

                // Try to pass a DB Object as DSN

        $this->_NeSe2 = DB_NestedSet::factory(
                DB_NESTEDSET_TEST_DRIVER, $this->_NeSe->db, $params);

        $this->_NeSe2->setAttr(array
        (
        'node_table' => 'tb_nodes2',
        'lock_table' => 'tb_locks',
        'lockTTL'    => 5,
        'debug' => 0)
        );
    }

    function tearDown() {
        if (!$this->_NeSe || !$this->_NeSe->node_table) {
            return;
        }
        $tb = $this->_NeSe->node_table;
        $sql = "DELETE FROM $tb";
        $this->_NeSe->db->query($sql);
    }

    // +----------------------------------------------+
    // | Internal helper methods                      |
    // |----------------------------------------------+
    // | [PRIVATE]                                    |
    // +----------------------------------------------+
    function _moveTree__Across($branches, $mvt, $nodecount) {
        foreach($branches[0] AS $nodeid => $node) {
            foreach($branches[1] AS $tnodeid => $tnode) {
                $ret = $this->_NeSe->moveTree($nodeid, $tnodeid, $mvt);
                if (PEAR::isError($ret)) {
                    continue;
                }
                $mnode = $this->_NeSe->pickNode($ret, true);
                $this->assertEquals($ret, $nodeid, 'Nodeid was not returned as expected');
                $this->assertEquals($nodecount, count($this->_NeSe->getAllNodes(true)), 'Node count changed');
                $p = $this->_NeSe->getParent($nodeid, true);

                if ($mvt == NESE_MOVE_BELOW) {
                    $this->assertEquals($tnode['id'], $p['id'], 'Move below failed (parent ID)');
                }

                if ($mnode['id'] != $mnode['rootid']) {
                    $this->assertEquals($p['id'], $mnode['parent'], 'Parent ID is wrong');
                    if ($p['id'] != $mnode['parent']) {
                        // <DEBUG>
                        // <DEBUG>
                        // echo "\n<pre>\n";
                        print_r($p);
                        // echo "\n</pre>\n";
                        // </DEBUG>
                        // <DEBUG>
                        // echo "\n<pre>\n";
                        print_r($mnode);
                        // echo "\n</pre>\n";
                        // </DEBUG>
                        // </DEBUG>
                    }
                }
            }
        }
    }

    function _deleteNodes($parentID, $keep = false) {
        $children = $this->_NeSe->getChildren($parentID, true);
        $dc = 0;
        if (is_array($children)) {
            $cct = count($children);
            $randval = $randval = mt_rand(0, $cct-1);
            foreach($children AS $cid => $child) {
                // Randomly delete some trees top down instead of deleting bottom up
                // and see if the result is still O.K.
                if ($dc == $randval) {
                    $this->_NeSe->deleteNode($cid);
                    $this->assertFalse($this->_NeSe->pickNode($cid, true), 'pickNode didn not return false after node deletion.');
                    continue;
                }

                if ($child['r']-1 != $child['l']) {
                    $this->_deleteNodes($cid);
                }
                $currchild = $this->_NeSe->pickNode($cid, true);
                // The next remaining child in the tree should always have the order 1
                $this->assertEquals(1, $currchild['norder'], 'Child has wrong order');

                $this->assertEquals($currchild['l'], $currchild['r']-1, 'Wrong lft-rgt checksum after child deletion.');
                $this->_NeSe->deleteNode($cid);
                $this->assertFalse($this->_NeSe->pickNode($cid, true), 'pickNode didn not return false after node deletion.');
                $dc++;
            }
        } elseif (!$keep) {
            $parent = $this->_NeSe->pickNode($parentID, true);
            $this->assertEquals($parent['l'], $parent['r']-1, 'Wrong lft-rgt checksum after child deletion.');
            $this->_NeSe->deleteNode($parentID);
            $this->assertTrue($this->_NeSe->pickNode($parentID, true), 'pickNode didn not return false after node deletion.');
        }
    }

    function _setupRootnodes($nbr) {
        $nodes = array();
        $lnid = false;
        // Create some rootnodes
        for($i = 0;$i < $nbr;$i++) {
            $nodeIndex = $i + 1;
            $values = array();
            $values['STRNA'] = 'Node ' . $nodeIndex;
            // Test quoting of reserved words
            $detNext = true;
            $values['key'] = 'SELECT';
            if ($i == 0) {
                $nid[$i] = $this->_NeSe->createRootnode($values, false, true);
            } else {
                if($detNext) {
                    $nid[$i] = $this->_NeSe->createRootnode($values);
                } else {
                    $nid[$i] = $this->_NeSe->createRootnode($values, $nid[$i-1]);
                }
            }

            $this->assertEquals($nodeIndex, $nid[$i], 'Rootnode $nodeIndex: creation failed');
        }
        $this->assertEquals($nbr, count($nid), "RootNode creation went wrong.");
        return $nid;
    }

    function _createRandomNodes($rnc, $nbr) {
        $rootnodes = $this->_createRootNodes($rnc);
        // Number of nodes to create
        $available_parents = array();
        $relationTree = array();
        foreach($rootnodes AS $rid => $rootnode) {
            $available_parents[] = $rid;
        }

        for($i = 0; $i < $nbr-1; $i++) {
            $randval = mt_rand(0, count($available_parents)-1);
            $choosemethod = mt_rand(1, 2);
            $target = $this->_NeSe->pickNode($available_parents[$randval], true);
            $nindex = $i;
            $values = array();
            $returnID = false;
            if ($choosemethod == 1) {
                $method = 'createSubNode';
                $exp_target_lft_after = $target['l'];
                $exp_target_rgt_after = $target['r'] + 2;
                $values['STRNA'] = $target['name'] . '.' . $nindex;
                // Test quoting of reserved words
                $values['key'] = 'SELECT';
                $parentid = $target['id'];
            } else {
                $method = 'createRightNode';
                $returnID = true;

                if (isset($relationTree[$target['id']]['parent'])) {
                    $parentid = $relationTree[$target['id']]['parent'];
                    $parent = $this->_NeSe->pickNode($parentid, true);
                    $exp_target_lft_after = $parent['l'];
                    $exp_target_rgt_after = $parent['r'] + 2;
                } else {
                    $parentid = false;
                }
                if (isset($relationTree[$parentid]['children'])) {
                    $cct = count($relationTree[$parentid]['children']) + 1 ;
                } else {
                    $cct = 1;
                }

                if (!empty($parent)) {
                    $values['STRNA'] = $parent['name'] . '.' . $cct;
                    // Test quoting of reserved words
                    $values['key'] = 'SELECT';
                } else {
                    $rootnodes = $this->_NeSe->getRootNodes(true);
                    $cct = count($rootnodes) + 1;
                    $values['STRNA'] = 'Node ' . $cct;
                    // Test quoting of reserved words
                    $values['key'] = 'SELECT';
                }
            }

            $available_parents[] = $nid = $this->_NeSe->$method($target['id'], $values, $returnID);

            $target_after = false;
            if ($method == 'createSubNode') {
                $target_after = $this->_NeSe->pickNode($target['id'], true);
            } elseif ($parentid) {
                $target_after = $this->_NeSe->pickNode($parent['id'], true);
            }

            if ($target_after) {
                $this->assertEquals($exp_target_lft_after, $target_after['l'], "Wrong LFT after $method");
                $this->assertEquals($exp_target_rgt_after, $target_after['r'], "Wrong RGT after $method");
            }
            if ($choosemethod == 1) {
                // createSubNode()
                $relationTree[$nid]['parent'] = $parentid;
                $relationTree[$target['id']]['children'][] = $nid;
                $exp_rootid = $target['rootid'];
            } else {
                // createRightNode()
                if ($parentid) {
                    $exp_rootid = $parent['rootid'];
                } else {
                    $exp_rootid = $nid;
                }
                $relationTree[$parentid]['children'][] = $nid;
                $relationTree[$nid]['parent'] = $parentid;
            }
            $cnode = $this->_NeSe->pickNode($nid, true);
            // Test rootid
            $this->assertEquals($exp_rootid, $cnode['rootid'], "Node {$cnode['name']}: Wrong root id.");
        }

        $exp_cct = 0;
        $cct = 0;
        // Traverse the tree and verify it using getChildren
        foreach($rootnodes AS $rid => $rootnode) {
            $rn = $this->_NeSe->pickNode($rid, true);
            $cct = $cct + $this->_traverseChildren($rn, $relationTree);
            // Calc the expected number of children from lft-rgt
            $exp_cct = $exp_cct + floor(($rn['r'] - $rn['l']) / 2);
        }
        // Test if all created nodes got returned
        $this->assertEquals($exp_cct, $cct, 'Total node count returned is wrong');

        return $relationTree;
    }

    function _createRootNodes($nbr, $dist = false) {
        // Creates 10 rootnodes
        $rplc = array();
        $nodes = $this->_setupRootnodes($nbr);

        $disturbidx = false;
        $disturb = false;
        $disturbSet = false;
        // Disturb the order by adding a node in the middle of the set
        if ($dist) {
            $values = array();
            $values['STRNA'] = 'disturb';
            // Test quoting of reserved words
            $values['key'] = 'SELECT';
            // Try to overwrite the rootid which should be set inside the method
            // $values['ROOTID'] = -100;
            $disturbidx = count($nodes);
            $disturb = 6;
            $nodes[$disturbidx] = $this->_NeSe->createRootnode($values, $disturb);
        }

        for($i = 0; $i < count($nodes); $i++) {
            $node[$nodes[$i]] = $this->_NeSe->pickNode($nodes[$i], true);

            $nodeIndex = $i + 1;

            if (!empty($disturb) && $nodeIndex - 1 == $disturb) {
                $disturbSet = true;
            }

            if (!$disturbSet) {
                $exp_order = $nodeIndex;
                $exp_name = 'Node ' . $nodeIndex;
            } elseif ($i == $disturbidx) {
                $exp_order = $disturb + 1;
                $exp_name = 'disturb';
            } else {
                $exp_order = $nodeIndex + 1;
                $exp_name = 'Node ' . $nodeIndex;
            }
            // Test Array
            $this->assertTrue(is_array($node[$nodes[$i]]), "Rootnode $nodeIndex: No array given.");
            // Test NodeID==RootID
            $this->assertEquals($node[$nodes[$i]]['id'], $node[$nodes[$i]]['rootid'], "Rootnode $nodeIndex: NodeID/RootID not equal.");
            // Test lft/rgt
            $this->assertEquals(1, $node[$nodes[$i]]['l'], "Rootnode $nodeIndex: LFT has to be 1");
            $this->assertEquals(2, $node[$nodes[$i]]['r'], "Rootnode $nodeIndex: RGT has to be 2");
            // Test order
            $this->assertEquals($exp_order, $node[$nodes[$i]]['norder'], "Rootnode $nodeIndex: Wrong order.");
            // Test Level
            $this->assertEquals(1, $node[$nodes[$i]]['level'], "Rootnode $nodeIndex: Wrong level.");
            // Test Name
            $this->assertEquals($exp_name, $node[$nodes[$i]]['name'], "Rootnode $nodeIndex: Wrong name.");
        }
        return $node;
    }

    function _createSubNode($rnc, $depth, $npl) {
        $rootnodes = $this->_createRootNodes($rnc);

        $init = true;
        foreach ($rootnodes as $id => $parent) {
            $relationTree = $this->_recursCreateSubNode($id, $npl, $parent['name'], 1, $depth, $init);
            $init = false;
        }
        return $relationTree;
    }

    function _recursCreateSubNode($pid, $npl, $pname, $currdepth, $maxdepth, $init = false) {
        static $relationTree;
        if ($init) {
            $relationTree = array();
        }
        if ($currdepth > $maxdepth) {
            return $relationTree;
        }

        $newdepth = $currdepth + 1;
        for($i = 0; $i < $npl; $i++) {
            $nindex = $i + 1;
            $values = array();
            $values['STRNA'] = $pname . '.' . $nindex;
            // Test quoting of reserved words
            $values['key'] = 'SELECT';
            // Try to overwrite the rootid which should be set inside the method
            $values['STRID'] = -100;

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
            $this->assertTrue(is_array($nnode), "Node {$values['STRNA']}: No array given.");
            // Test rootid
            $this->assertEquals($exp_rootid, $nnode['rootid'], "Node {$values['STRNA']}: Wrong rootid");
            // Test lft/rgt
            $this->assertEquals($exp_lft, $nnode['l'], "Node {$values['STRNA']}: Wrong LFT");
            $this->assertEquals($exp_rgt, $nnode['r'], "Node {$values['STRNA']}: Wrong RGT");
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

    function _traverseChildren($current_node, $relationTree = array(), $reset = true) {
        static $occvals;

        if ($reset || !isset($occvals)) {
            $occvals = array();
        }

        $level = $current_node['level'];

        $children = $this->_NeSe->getChildren($current_node['id'], true);

        if (!empty($relationTree)) {
            if (is_array($exp_children = $this->_traverseChildRelations($relationTree, $current_node['id'], false, true))) {
                if (count($exp_children) == 0) {
                    $exp_children = false;
                } else {
                    $exp_children = array_reverse($exp_children, true);
                }
            }
            // Test if the children fetched with API calls matches the children from the relationTree
            $this->assertEquals($exp_children, $children, "Node {$current_node['name']}: Children don't match children from relation tree.");
        }

        $x = 0;
        $lcct = 0;

        if ($children) {
            $level++;
            foreach($children AS $cid => $child) {
                // Test order
                $exp_order = $x + 1;
                $exp_level = $level;
                $exp_rootid = $current_node['rootid'];
                $this->assertEquals($exp_order, $child['norder'], "Node {$current_node['name']}: Wrong order value.");
                // Test rootid
                $this->assertEquals($exp_rootid, $child['rootid'], "Node {$current_node['name']}: Wrong root id.");
                // Test level
                $this->assertEquals($exp_level, $child['level'], "Node {$current_node['name']}: Wrong level value.");
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

    function _traverseParentRelations($relationTree, $nid, $init = false) {
        static $relationNodes;
        if ($init) {
            $relationNodes = array();
        }

        if (empty($relationTree[$nid]['parent'])) {
            return $relationNodes;
        }
        $parentID = $relationTree[$nid]['parent'];
        $relationNodes[$parentID] = $this->_NeSe->pickNode($parentID, true);
        $this->_traverseParentRelations($relationTree, $parentID);
        return $relationNodes;
    }

    function _traverseChildRelations($relationTree, $nid, $deep = false, $init = false) {
        static $relationNodes;
        if ($init) {
            $relationNodes = array();
        }

        if (empty($relationTree[$nid]['children'])) {
            return $relationNodes;
        }
        $children = $relationTree[$nid]['children'];

        for($i = 0;$i < count($children);$i++) {
            $cid = $children[$i];
            $relationNodes[$cid] = $this->_NeSe->pickNode($cid, true);
            if ($deep) {
                $this->_traverseChildRelations($relationTree, $cid, $deep);
            }
        }
        return $relationNodes;
    }

    function _indentTree($tree) {
        return;

        echo "\n";
        foreach($tree AS $nid => $node) {
            printf('%s %02d-%02d [%02d|%02d|%02d] | %s (%s)',
                str_repeat('-', $node['level']),
                $node['l'],
                $node['r'],
                $node['level'],
                $node['norder'],
                $node['rootid'],
                $node['name'],
                $node['id']);
            echo "\n";
        }
         echo "\n";
    }
}
