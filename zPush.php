<?php
/**
 * This is the only file you need to include in order to bring this library into your project.
 * It will register an autoloader function for the rest of the project, and classes will be loaded as-needed.
 *
 * Created by dougw on 10/15/13 5:51 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

define('zPUSH_ROOT_DIR', dirname(__FILE__));

// Library class loader
function zPushClassAutoloader($class) {

	static $classes = array(
		// APNS
		'ZAPNSError'    => '/APNS/ZAPNSError.php',
		'ZAPNSGateway'  => '/APNS/ZAPNSGateway.php',
		'ZAPNSMessage'  => '/APNS/ZAPNSMessage.php',
		'ZAPNSQueue'    => '/APNS/ZAPNSQueue.php',

		// C2DM

		// UTIL
		'ZJSONTools'    => '/Util/ZJSONTools.php',
		'ZPushLogging'  => '/Util/ZPushLogging.php'
	);

	if (isset($classes[$class])) {
		include zPUSH_ROOT_DIR . $classes[$class];
		return;
	}
}

spl_autoload_register('zPushClassAutoloader');
