<?php
// ini_set('error_reporting',E_ALL);
ini_set('include_path',realpath(dirname(__FILE__).'/../').PATH_SEPARATOR.ini_get('include_path'));

require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'DB/NestedSet.php';



require_once 'UnitTest.php';

require_once 'NestedSet/api.php';

require_once 'NestedSet/creation.php';

require_once 'NestedSet/manipulation.php';

require_once 'NestedSet/query.php';

/**
 * Static test suite.
 */
class DB_NestedSetTestsuite extends PHPUnit_Framework_TestSuite {
  
  /**
   * Constructs the test suite handler.
   */
  public function __construct() {
    $this->backupGlobals = false;

    $this->setName('DB_NestedSetTestsuite');
    
    $this->addTestSuite('tests_NestedSet_api');
    
    $this->addTestSuite('tests_NestedSet_creation');
    
    $this->addTestSuite('tests_NestedSet_manipulation');
    
    $this->addTestSuite('tests_NestedSet_query');
  
  }
  
  /**
   * Creates the suite.
   */
  public static function suite() {
    return new self();
  }
}

