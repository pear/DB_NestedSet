<?php
require_once 'DB/NestedSet/TestCase.php';

class DB_NestedSet_APITest extends DB_NestedSet_TestCase {

    function test_sortMethods() {

        // Rootnodes

        echo "<code>";
        $values['STRNA'] = 'Skireisen';
        $skireisen = $this->_NeSe->createRootNode($values, false, true);


        $values['STRNA'] = 'Schweiz';
        $schweiz = $this->_NeSe->createSubNode($skireisen, $values);
        $values['STRNA'] = 'Grindelwald';
        $grindelwald = $this->_NeSe->createSubNode($schweiz, $values);



        $values['STRNA'] = 'Österreich';
        $oesterreich = $this->_NeSe->createSubNode($skireisen, $values);
        $values['STRNA'] = 'Arlberg';
        $arlberg = $this->_NeSe->createSubNode($oesterreich, $values);
        $values['STRNA'] = 'Saalbach';
        $saalbach = $this->_NeSe->createSubNode($oesterreich, $values);
        $values['STRNA'] = 'Obertauern';
        $obertauern = $this->_NeSe->createSubNode($oesterreich, $values);

        $values['STRNA'] = 'Italien';
        $italien = $this->_NeSe->createSubNode($skireisen, $values);
        $values['STRNA'] = 'Meransen';
        $meransen = $this->_NeSe->createSubNode($italien, $values);


        echo "DEFAULT\n";
        $this->_NeSe->setSortMode(NESE_SORT_LEVEL);
        $allnodes_default = $this->_NeSe->getAllNodes(true);
        $this->_indentTree($allnodes_default);

        echo "BY NAME\n";
        $this->_NeSe->setSortMode(NESE_SORT_LEVEL);
        $this->_NeSe->secondarySort = 'STRNA';
        $allnodes_byname = $this->_NeSe->getAllNodes(true);
        $this->_indentTree($allnodes_byname);

        echo "PREORDER\n";
        $this->_NeSe->setSortMode(NESE_SORT_PREORDER);
        $this->_NeSe->secondarySort = 'STREH';
        $allnodes_preorder = $this->_NeSe->getAllNodes(true);
        $this->_indentTree($allnodes_preorder);

        echo "BY NAME PREORDER\n";
        $this->_NeSe->setSortMode(NESE_SORT_PREORDER);
        $this->_NeSe->secondarySort = 'STRNA';
        $allnodes_byname_pre = $this->_NeSe->getAllNodes(true);
        $this->_indentTree($allnodes_byname_pre);

        echo "BY NAME PREORDER getSubBranch\n";
        $this->_NeSe->setSortMode(NESE_SORT_PREORDER);
        $this->_NeSe->secondarySort = 'STRNA';
        $allnodes_preorder = $this->_NeSe->getSubBranch($oesterreich, true);
        $this->_indentTree($allnodes_preorder);

        echo "BY NAME PREORDER getSubBranch empty subbranch\n";
        $this->_NeSe->setSortMode(NESE_SORT_PREORDER);
        $this->_NeSe->secondarySort = 'STRNA';
        $allnodes_preorder = $this->_NeSe->getSubBranch($grindelwald, true);
        $this->_indentTree($allnodes_preorder);



        echo "TRYING getBranch()\n";
        $atbranch = $this->_NeSe->getBranch($oesterreich, true);
        $this->_indentTree($atbranch);

        echo "TRYING getChildren()\n";
        $atbranch = $this->_NeSe->getChildren($oesterreich, true);
        $this->_indentTree($atbranch);

        echo "TRYING getSubBranch()\n";
        $atbranch = $this->_NeSe->getSubBranch($skireisen, true);
        $this->_indentTree($atbranch);
        echo "</code>";
    }

    function test_convertModel() {
        $rnc = 3;
        $depth = 1;
        $npl = 2;
        $this->_createSubNode($rnc, $depth, $npl);
        DB_NestedSet::convertTreeModel($this->_NeSe, $this->_NeSe2);
        $this->_NeSe->setSortMode(NESE_SORT_LEVEL);
        $this->_NeSe2->setSortMode(NESE_SORT_LEVEL);
        $this->assertEquals($this->_NeSe->getAllNodes(true), $this->_NeSe2->getAllNodes(true), 'Converted tree should match original');
    }
}