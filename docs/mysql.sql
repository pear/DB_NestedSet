# The table which holds the structure
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

# A table which is used for a little table locking to avoid conflicts
CREATE TABLE tb_locks (
  lockID char(32) NOT NULL default '',
  lockTable char(32) NOT NULL default '',
  lockStamp int(11) NOT NULL default '0',
  PRIMARY KEY  (lockID,lockTable)
) TYPE=MyISAM COMMENT='Table locks for NestedSet';

