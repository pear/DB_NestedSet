<?php /** $Id$ */
/**
 * Tests the DB_NestedSet class using the TreeMenu renderer
 * 
 * Requires that you have installed GraphViz. It is available on a 
 * variety of platforms, including windows and linux.
 * 
 * You can go to www.graphviz.org
 * 
 * You also need PEAR::Image_GraphViz
 * pear install Image_GraphViz
 *
 * @author Jason Rust <jrust@rustyparts.com>
 * @author Arnaud Limbourg <arnaud@php.net>
 */
/**
 * Dump of the example mysql table and data:
#
# Table structure for table `nested_set`
#

drop table if exists nested_set;
CREATE TABLE `nested_set` (
  `id` int(10) unsigned NOT NULL default '0',
  `parent_id` int(10) unsigned NOT NULL default '0',
  `order_num` tinyint(4) unsigned NOT NULL default '0',
  `level` int(10) unsigned NOT NULL default '0',
  `left_id` int(10) unsigned NOT NULL default '0',
  `right_id` int(10) unsigned NOT NULL default '0',
  `parent` int(10) default null,
  `name` varchar(60) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `right` (`right_id`),
  KEY `left` (`left_id`),
  KEY `order` (`order_num`),
  KEY `level` (`level`),
  KEY `parent_id` (`parent_id`),
  KEY `right_left` (`id`,`parent_id`,`left_id`,`right_id`)
) TYPE=MyISAM;

#
# Dumping data for table `nested_set`
#

INSERT INTO `nested_set` VALUES (5, 5, 1, 1, 1, 10, 0, 'Root A');
INSERT INTO `nested_set` VALUES (7, 7, 1, 1, 1, 4, 0, 'Root B');
INSERT INTO `nested_set` VALUES (6, 5, 1, 2, 2, 5, 5, 'Sub1 of A');
INSERT INTO `nested_set` VALUES (1, 5, 2, 2, 6, 9, 5, 'Sub2 of A');
INSERT INTO `nested_set` VALUES (2, 5, 1, 3, 3, 4, 6,'Child of Sub1');
INSERT INTO `nested_set` VALUES (3, 5, 1, 3, 7, 8, 1, 'Child of Sub2');
INSERT INTO `nested_set` VALUES (4, 7, 1, 2, 2, 3, 7, 'Sub of B');
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

require_once('DB/NestedSet.php');
require_once('DB/NestedSet/Output.php');

$dsn = 'mysql://user:pass@localhost/test_viz';

// Please pu the full path to the dot command
$dot_command = 'c:/progra~1/att/graphviz/bin/dot.exe';

$params = array(
    'id'        => 'id',
    'parent_id' => 'rootid',
    'left_id'   => 'l',
    'right_id'  => 'r',
    'order_num' => 'norder',
    'level'     => 'level',
    'name'      => 'name',
    'parent'    => 'parent'
);

$nestedSet =& DB_NestedSet::factory('DB', $dsn, $params);
// we want the nodes to be displayed ordered by name, so we add the secondarySort attribute
$nestedSet->setAttr(array(
        'node_table' => 'nested_set',
        'lock_table' => 'nested_set_locks'
    )
);
// get data (important to fetch it as an array, using the true flag)
$data = $nestedSet->getAllNodes(true);

// add labels to the arrows between two links
foreach ($data as $id => $node) {
     $data[$id]['edgeLabel'] = 'from ' . $node['parent'] . ' to ' . $node['id'];
}

$params = array(
    'structure' => $data,
    'nodeLabel' => 'name'
);

$output =& DB_NestedSet_Output::factory($params, 'graphviz');
$output->printTree($dot_command, 'png');
?>