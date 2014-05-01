<?php
/**
 * Created by dougw on 10/18/13 4:32 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

define('zPUSH_TEST_ROOT_DIR', dirname(__FILE__));

// Load main project bootstrap
require_once zPUSH_TEST_ROOT_DIR . '/../zPush.php';

// Newer phpunit installs use autoloading, so this is unnecessary
@include_once 'PHPUnit/Framework.php';

// Library class loader
function zPushTestClassAutoloader($class) {

	static $classes = array(
		// APNS
		'ZAPNSErrorTest'    => '/APNS/ZAPNSErrorTest.php',
		'ZAPNSGatewayTest'  => '/APNS/ZAPNSGatewayTest.php',
		'ZAPNSMessageTest'  => '/APNS/ZAPNSMessageTest.php',
		'ZAPNSQueueTest'    => '/APNS/ZAPNSQueueTest.php',

		// C2DM

		// UTIL
		'ZJSONToolsTest'    => '/Util/ZJSONToolsTest.php'
	);

	if (isset($classes[$class])) {
		include zPUSH_TEST_ROOT_DIR . $classes[$class];
		return;
	}
}

spl_autoload_register('zPushTestClassAutoloader');
