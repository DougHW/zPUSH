<?php
/**
 * Created by dougw on 10/18/13 4:31 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

require_once dirname(__FILE__) . '/zPushTestConfig.php';

class ZTestSuiteAll
{
	public static function suite() {
		$suite = new PHPUnit_Framework_TestSuite();

		$suite->setName('zPushTestAllSuite');

		$suite->addTestSuite('ZAPNSQueueTest');

		$suite->addTestSuite('ZJSONToolsTest');

		return $suite;
	}
}