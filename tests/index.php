<?php

/**
* UnitTest
* Unit test interface for DB_NestedSet
*
* @author       Daniel Khan <dk@webcluster.at>
* @package      DB_NestedSet
* @version      $Revision$
* @access       public
*/


ini_set('include_path',realpath(dirname(__FILE__).'/../').PATH_SEPARATOR.ini_get('include_path'));

require_once 'PHPUnit.php';
require_once 'TestBase.php';
require_once 'PHPUnit/GUI/HTML.php';
require_once 'NestedSet.php';
require_once 'UnitTest.php';
ini_set('error_reporting',E_ALL);

require_once 'PHPUnit/GUI/SetupDecorator.php';

$gui = new PHPUnit_GUI_SetupDecorator(new PHPUnit_GUI_HTML());
$gui->getSuitesFromDir(dirname(__FILE__),'.*[^_]\.php$',array('UnitTest.php','index.php','clitest.php','TestBase.php', 'NestedSet/CVS'));
$gui->show();

?>
