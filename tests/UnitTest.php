<?php
/**
* UnitTest
* Unit test interface for DB_NestedSet
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSetTest
* @version      $Revision$
* @access       public
*/
class DB_NestedSetTest extends TestBase  {


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

        $db_driver = 'MDB2';
        $db_dsn    = 'mysql://user:password@localhost/test';
        $this->_NeSe = DB_NestedSet::factory($db_driver, $db_dsn, $params);
        $this->_NeSe->setAttr(array
        (
        'node_table' => 'tb_nodes',
        'lock_table' => 'tb_locks',
        'lockTTL'    => 5,
        'debug' => 0)
        );

                // Try to pass a DB Object as DSN

        $this->_NeSe2 = DB_NestedSet::factory($db_driver, $this->_NeSe->db, $params);

        $this->_NeSe2->setAttr(array
        (
        'node_table' => 'tb_nodes2',
        'lock_table' => 'tb_locks',
        'lockTTL'    => 5,
        'debug' => 0)
        );
    }

    function tearDown() {

        $tb = $this->_NeSe->node_table;
        $sql = "DELETE FROM $tb";
        $this->_NeSe->db->query($sql);
    }
}
?>