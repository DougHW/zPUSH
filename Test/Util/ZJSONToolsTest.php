<?php
/**
 * Created by dougw on 10/18/13 4:36 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

require_once dirname(__FILE__) . '/../zPushTestConfig.php';

class ZJSONToolsTest extends PHPUnit_Framework_TestCase
{
	public function setUp() {

	}

	/**
	 * Tests that the JSON encoding function works properly.
	 * NOTE - This will only test that it works correctly on the installed version of PHP. This funciton is
	 * works differently on different versions, and should be tested on PHP < 5.4 and PHP >= 5.4
	 */
	public function testJSONEncodeWithUnicode() {
		$testPhraseGreek = "Μιλήστε τι σκέφτεστε σήμερα στα λόγια όσο πιο σκληρά μπάλες κανονιού, και αύριο να μιλήσει αύριο τι σκέφτεται με σκληρά λόγια και πάλι, αν και έρχονται σε αντίθεση με κάθε πράγμα που είπε σήμερα.";

		$jsonEncodedPhrase = ZJSONTools::jsonEncodeWithUnicode($testPhraseGreek);

		$searchLoc = strpos($jsonEncodedPhrase, '\u');

		$this->assertTrue($searchLoc === false, 'Unicode escape found in encoded JSON!');

		$unencodedPhrase = json_decode($jsonEncodedPhrase);

		$this->assertTrue($testPhraseGreek === $unencodedPhrase, 'JSON encoding not transitive!');
	}
}
