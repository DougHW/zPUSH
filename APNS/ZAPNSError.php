<?php
/**
 * Created by dougw on 10/4/13 5:16 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

/**
 * Encapsulates an error response from Apple for an APNS message
 *
 * Class ZAPNSError
 */
class ZAPNSError
{
	/**
	 * APNS status constants
	 * @see https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW4
	 */
	const STATUS_NO_ERROR               = 0;
	const STATUS_PROCESSING_ERROR       = 1;
	const STATUS_MISSING_TOKEN          = 2;
	const STATUS_MISSING_TOPIC          = 3;
	const STATUS_MISSING_PAYLOAD        = 4;
	const STATUS_INVALID_TOKEN_SIZE     = 5;
	const STATUS_INVALID_TOPIC_SIZE     = 6;
	const STATUS_INVALID_PAYLOAD_SIZE   = 7;
	const STATUS_INVALID_TOKEN          = 8;
	const STATUS_SHUTDOWN               = 10;
	const STATUS_UNKNOWN                = 255;

	const STATUS_SOCKET_ERROR           = 100;  // Socket level error. Not a response from Apple.

	public $statusCode;

	public $uniqueId;

	public function __construct($statusCode, $uniqueId = null)
	{
		$this->statusCode = $statusCode;
		$this->uniqueId = $uniqueId;
	}

	public function __toString()
	{
		switch ($this->statusCode) {
			case self::STATUS_NO_ERROR:
				return 'No Error';
			case self::STATUS_PROCESSING_ERROR:
				return 'Processing Error';
			case self::STATUS_MISSING_TOKEN:
				return 'Missing Token';
			case self::STATUS_MISSING_TOPIC:
				return 'Missing Topic';
			case self::STATUS_MISSING_PAYLOAD:
				return 'Missing Payload';
			case self::STATUS_INVALID_TOKEN_SIZE:
				return 'Invalid Token Size';
			case self::STATUS_INVALID_TOPIC_SIZE:
				return 'Invalid Topic Size';
			case self::STATUS_INVALID_PAYLOAD_SIZE:
				return 'Invalid Payload Size';
			case self::STATUS_INVALID_TOKEN:
				return 'Invalid Token';
			case self::STATUS_SHUTDOWN:
				return 'Service Unavailable';
			case self::STATUS_UNKNOWN:
				return 'Unknown';

			case self::STATUS_SOCKET_ERROR:
				return 'Socket Error';
		}

		return '';
	}
}