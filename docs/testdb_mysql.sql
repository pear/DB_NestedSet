# $Id$
#
# mySQL Database structure for unit tests provided by DB_NestedSet
#
# @author       Daniel Khan <dk@webcluster.at>
# @package      DB_NestedSet
# @version      $Revision$
# @access       public
#
# Table structure for table `tb_locks`
#

CREATE TABLE `tb_locks` (
  `lockID` char(32) NOT NULL default '',
  `lockTable` char(32) NOT NULL default '',
  `lockStamp` int(11) NOT NULL default '0',
  PRIMARY KEY  (`lockID`,`lockTable`)
) TYPE=MyISAM COMMENT='Table locks for NestedSet';

# --------------------------------------------------------

#
# Table structure for table `tb_nodes`
#

CREATE TABLE tb_nodes (
  STRID int(11) NOT NULL auto_increment,
  ROOTID int(11) NOT NULL default '0',
  l int(11) NOT NULL default '0',
  r int(11) NOT NULL default '0',
  PARENT int(11) NOT NULL default '0',
  STREH int(11) NOT NULL default '0',
  LEVEL int(11) NOT NULL default '0',
  STRNA char(128) NOT NULL default '',
  PRIMARY KEY  (STRID),
  KEY ROOTID (ROOTID),
  KEY STREH (STREH),
  KEY l (l),
  KEY r (r),
  KEY LEVEL (LEVEL),
  KEY SRLR (ROOTID,l,r),
  KEY parent (PARENT)
) TYPE=MyISAM COMMENT='NestedSet table';

# --------------------------------------------------------

#
# Table structure for table `tb_nodes_STRID_seq`
#

CREATE TABLE `tb_nodes_STRID_seq` (
  `id` int(10) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`id`)
) TYPE=MyISAM;