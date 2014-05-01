<?php
/**
 * Created by dougw on 10/9/13 5:21 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

class ZJSONTools
{
	const LOG_TAG = 'zPUSH.Util.ZJSONTools';

	/**
	 * This function facilitates JSON encoding without escaped unicode sequences in all versions of PHP.
	 *
	 * For example:
	 * Simon1010 \u0e04\u0e37\u0e2d\u0e04\u0e19\u0e17\u0e35\u0e48\u0e43\u0e0a\u0e48\u0e04\u0e19\u0e43\u0e2b\u0e21\u0e48\u0e08\u0e32\u0e01 ZSMS \u0e17\u0e35\u0e48 Zoosk
	 * would become:
	 * Simon1010 คือคนที่ใช่คนใหม่จาก ZSMS ที่ Zoosk
	 *
	 * @param $stringToEncode
	 * @return mixed|string
	 */
	public static function jsonEncodeWithUnicode($stringToEncode)
	{
		if (defined('JSON_UNESCAPED_UNICODE')) {
			return json_encode($stringToEncode, JSON_UNESCAPED_UNICODE);
		} else {
			/**
			 * Credit for solution
			 * @see http://stackoverflow.com/a/2934602/205192
			 */
			return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', array('ZJSONTools', 'encodeEscapedUnicode'), json_encode($stringToEncode));
		}
	}

	/**
	 * This is a helper function to support unicode json encodings.
	 * @see http://stackoverflow.com/a/2934602/205192
	 *
	 * @param $match
	 * @return string
	 */
	private function encodeEscapedUnicode($match) {
		return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
	}
}
