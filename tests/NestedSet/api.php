<?php
class tests_NestedSet_api extends DB_NestedSetTest  {

    function test_sortMethods() {
        
        // Rootnodes
        $this->_NeSe->sortmode = NESE_SORT_LEVEL;
        $values['STRNA'] = 'A1';
        
        $node_a1 = $this->_NeSe->createRootNode($values, false, true); 
        $values['STRNA'] = 'C1';

        $node_c1 = $this->_NeSe->createLeftNode($node_a1, $values);
        $values['STRNA'] = 'B1';
        $node_b1 = $this->_NeSe->createRightNode($node_a1, $values, true); 
    
        
        // B1-1 Branch
        $values['STRNA'] = 'B1-1';
        $node_b11 = $this->_NeSe->createSubNode($node_b1, $values); 
        
        $values['STRNA'] = 'B1-1-1';
        $node_b111 = $this->_NeSe->createSubNode($node_b11, $values); 

        $values['STRNA'] = 'B1-1-1-1';
        $node_b1111 = $this->_NeSe->createSubNode($node_b111, $values); 
                 
        $values['STRNA'] = 'B1-1-2';
        $node_b112 = $this->_NeSe->createRightNode($node_b111, $values, true);     
      
        // B1-2 Branch
        $values['STRNA'] = 'B1-2';
        $node_b12 = $this->_NeSe->createSubNode($node_b1, $values); 
                
        $values['STRNA'] = 'B1-2-1';
        $node_b121 = $this->_NeSe->createSubNode($node_b12, $values); 

        $values['STRNA'] = 'B1-2-2';
        $node_b122 = $this->_NeSe->createLeftNode($node_b121, $values);         

        echo "DEFAULT\n";
        // Default sorting and ordering  
        $allnodes_default = $this->_NeSe->getAllNodes(true);
        $subbranch_b11_default = $this->_NeSe->getSubBranch($node_b11, true);
        $parents_b111_default = $this->_NeSe->getParents($node_b111, true);
        $children_b12_default = $this->_NeSe->getChildren($node_b12, true);

        $this->_indentTree($allnodes_default);
        $this->_indentTree($subbranch_b11_default);
        $this->_indentTree($parents_b111_default);
        $this->_indentTree($children_b12_default);

        echo "BY NAME\n";
        // Secondarysort to name
        $this->_NeSe->secondarySort = 'STRNA';
        $allnodes_byname = $this->_NeSe->getAllNodes(true);
        $subbranch_b11_byname = $this->_NeSe->getSubBranch($node_b11, true);
        $parents_b111_byname = $this->_NeSe->getParents($node_b111, true);
        $children_b12_byname = $this->_NeSe->getChildren($node_b12, true);
       
        $this->_indentTree($allnodes_byname);
        $this->_indentTree($subbranch_b11_byname);
        $this->_indentTree($parents_b111_byname);
        $this->_indentTree($children_b12_byname);
        
        echo "BY NAME PRE\n";
        // Sortmode to preorder
        $this->_NeSe->sortmode = NESE_SORT_PREORDER;
        $allnodes_default_pre = $this->_NeSe->getAllNodes(true);
        $allnodes_byname_pre = $this->_NeSe->getAllNodes(true);
        $subbranch_b11_byname_pre = $this->_NeSe->getSubBranch($node_b11, true);
        $parents_b111_byname_pre = $this->_NeSe->getParents($node_b111, true);
        $children_b12_byname_pre = $this->_NeSe->getChildren($node_b12, true);        
        
        $this->_indentTree($allnodes_byname_pre);
        $this->_indentTree($subbranch_b11_byname_pre);
        $this->_indentTree($parents_b111_byname_pre);
        $this->_indentTree($children_b12_byname_pre);
    }
}
?>