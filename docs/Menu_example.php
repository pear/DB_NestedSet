<?php /** $Id$ */ ?>
<html>
  <title>DB_NestedSet using PEAR::HTML_Menu Output class</title>
<body>
<div style="font-weight: bold;">DB_NestedSet using PEAR::HTML_Menu Output class</div>
<div>
<?php
/**
 * Tests the DB_NestedSet class using the Menu renderer
 * Requires that you have HTML_Menu installed
 *
 * @author Daniel Khan <dk@webcluster.at>
 */
// {{{ mysql dump

/**
 * Dump of the example mysql table and data:
#
# Table structure for table `nested_set`
#


CREATE TABLE `tb_nodes` (
  `STRID` int(11) NOT NULL auto_increment,
  `ROOTID` int(11) NOT NULL default '0',
  `l` int(11) NOT NULL default '0',
  `r` int(11) NOT NULL default '0',
  `parent` int(11) NOT NULL default '0',
  `STREH` int(11) NOT NULL default '0',
  `LEVEL` int(11) NOT NULL default '0',
  `STRNA` char(128) NOT NULL default '',
  PRIMARY KEY  (`STRID`),
  KEY `ROOTID` (`ROOTID`),
  KEY `STREH` (`STREH`),
  KEY `l` (`l`),
  KEY `r` (`r`),
  KEY `LEVEL` (`LEVEL`),
  KEY `SRLR` (`ROOTID`,`l`,`r`),
  KEY `parent` (`parent`)
) TYPE=MyISAM ;


#
# Dumping data for table `nested_set`
#

INSERT INTO `nested_set` VALUES (5, 5, 1, 1, 1, 10, 'Root A');
INSERT INTO `nested_set` VALUES (7, 7, 1, 1, 1, 4, 'Root B');
INSERT INTO `nested_set` VALUES (6, 5, 1, 2, 2, 5, 'Sub1 of A');
INSERT INTO `nested_set` VALUES (1, 5, 2, 2, 6, 9, 'Sub2 of A');
INSERT INTO `nested_set` VALUES (2, 5, 1, 3, 3, 4, 'Child of Sub1');
INSERT INTO `nested_set` VALUES (3, 5, 1, 3, 7, 8, 'Child of Sub2');
INSERT INTO `nested_set` VALUES (4, 7, 1, 2, 2, 3, 'Sub of B');
# --------------------------------------------------------

#
# Table structure for table `nested_set_locks`
#

CREATE TABLE `nested_set_locks` (
  `lockID` char(32) NOT NULL default '',
  `lockTable` char(32) NOT NULL default '',
  `lockStamp` int(11) NOT NULL default '0',
  PRIMARY KEY  (`lockID`,`lockTable`)
) TYPE=MyISAM COMMENT='Table locks for comments';

*/

// }}}
// {{{ set up variables
require_once('HTML/Menu.php');
require_once(dirname(__FILE__).'/../NestedSet.php');
require_once(dirname(__FILE__).'/../NestedSet/Output.php');
$dsn = 'mysql://user:password@localhost/test';
$params = array(
    'STRID'        => 'id',
    'ROOTID' => 'rootid',
    'l'   => 'l',
    'r'  => 'r',
    'STREH' => 'norder',
    'LEVEL'     => 'level',
    'STRNA'      => 'name', 
    'parent'	=> 'parent'
);

$nestedSet =& DB_NestedSet::factory('DB', $dsn, $params); 
// we want the nodes to be displayed ordered by name, so we add the secondarySort attribute
$nestedSet->setAttr(array(
        'node_table' => 'tb_nodes', 
        'lock_table' => 'tb_locks', 
        'secondarySort' => 'STRNA',
    )
);

    $parent = $nestedSet->createRootNode(array('STRNA' =>'Testberichte'), false, true);
    $nestedSet->createSubNode($parent, array('STRNA' => 'Pads,Sattelunterlagen'));
    $nestedSet->createSubNode($parent, array('STRNA' =>'Kartentaschen'));
    $nestedSet->createSubNode($parent, array('STRNA' =>'Kartenmesser'));
    $eh = $nestedSet->createSubNode($parent, array('STRNA' => 'Erste Hilfe Sets1'));
    $eh1 = $nestedSet->createSubNode($eh, array('STRNA' => 'Erste Hilfe Sets2'));
    $eh2 = $nestedSet->createSubNode($eh, array('STRNA' => 'Erste Hilfe Sets3'));
    $eh3 = $nestedSet->createSubNode($eh, array('STRNA' => 'Erste Hilfe Sets4'));
    $eh4 = $nestedSet->createSubNode($eh, array('STRNA' => 'Erste Hilfe Sets5'));
    $eh5 = $nestedSet->createSubNode($eh, array('STRNA' => 'Erste Hilfe Sets6'));
    $nestedSet->createSubNode($eh4, array('STRNA' => 'OutdoorJacken'));
    $nestedSet->createSubNode($parent, array('STRNA' =>'flexible Sattel'));



// get data (important to fetch it as an array, using the true flag)
// $data = $nestedSet->getAllNodes(true);
$data = $nestedSet->getSubBranch($eh, true);


// }}}
// {{{ manipulate data

// add links to each item
foreach ($data as $id => $node) {
     $data[$id]['url'] = $_SERVER['PHP_SELF'].'?nodeID=' . $node['id'];
}

// }}}
// {{{ render output
$params = array(
    'structure' => $data,
    'titleField' => 'name',
    'urlField' => 'url');

// Create the output driver object	
$output =& DB_NestedSet_Output::factory($params, 'Menu');

// Fetch the menu array
$structure = $output->returnStructure();


// Instantiate the menu object, we presume that $data contains menu structure
$currentUrl = $_SERVER['PHP_SELF'].'?nodeID=' . $_GET['nodeID'];

echo "Menu type 'sitemap'<br>";
$menu = & new HTML_Menu($structure, 'sitemap');

// Force menu to understand the nodeID passed with the request
$menu->forceCurrentUrl($currentUrl);
// Output the menu
$menu->show();
echo "<hr>";

echo "Menu type 'tree'<br>";
// Set another type
$menu->setMenuType('tree');
// Output the menu
$menu->show();
echo "<hr>";

echo "Menu type 'rows'<br>";
// Set another type
$menu->setMenuType('rows');
// Output the menu
$menu->show();
echo "<hr>";

echo "Menu type 'urhere'<br>";
// Set another type
$menu->setMenuType('urhere');
// Output the menu
$menu->show();
echo "<hr>";

echo "Menu type 'prevnext'<br>";
// Set another type
$menu->setMenuType('prevnext');
// Output the menu
$menu->show();
echo "<hr>";

// }}}
?>
</div>
</body>
</html>
