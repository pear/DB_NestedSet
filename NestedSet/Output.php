<?php
define('NESEO_ERROR_NO_METHOD',    'E1000');
define('NESEO_DRIVER_NOT_FOUND',   'E1100');

Class DB_NestedSet_Output {
	
	var $_structTreeMenu	= false;
	
		
	function &factory ($driver='TreeMenu',$params) {
		
		$path = dirname(__FILE__).'/'.$driver.'.php';
		
		if(is_dir($path) || !file_exists($path)) {
			PEAR::raiseError("The output driver '$driver' wasn't found", NESEO_DRIVER_NOT_FOUND, PEAR_ERROR_TRIGGER, E_USER_ERROR);
		}
		
		require_once($path);
		$driverClass = 'DB_NestedSet_'.$driver;
		return new $driverClass($params);
	}
	
	function setOptions($group, $options) {
		$this->options[$group] = $options;
	}
	
	function _getOptions($group) {
		if(!$this->options[$group]) {
			return array();	
		}
	}
		
	function printTree() {
		$this->raiseError("Method not available for this driver", NESEO_ERROR_NO_METHOD, PEAR_ERROR_TRIGGER, E_USER_ERROR);
	}
	
	function printListbox() {
		$this->raiseError("Method not available for this driver", NESEO_ERROR_NO_METHOD, PEAR_ERROR_TRIGGER, E_USER_ERROR);
	}
}
?>