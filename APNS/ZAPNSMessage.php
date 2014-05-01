<?php
/**
 * Created by dougw on 10/4/13 3:19 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

/**
 * Class ZAPNSMessage
 *
 * This class encapsulates all the information necessary to send an APNS notification to Apple.  See Apple documentation
 * for field meanings.
 *
 * @see https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Introduction.html
 */
class ZAPNSMessage
{
	const LOG_TAG = 'zPUSH.APNS.ZAPNSMessage';

	/**
	 * Defined by Apple
	 * @see https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/LegacyFormat.html#//apple_ref/doc/uid/TP40008194-CH105-SW5
	 */
	const MAXIMUM_PAYLOAD_BYTES = 256;

	/**
	 * @var string
	 */
	public $apnsToken;

	/**
	 * @var string
	 */
	public $message;

	/**
	 * @var int
	 */
	public $badgeCount;

	/**
	 * @var string
	 */
	public $sound;

	/**
	 * @var string
	 */
	public $customLink;

	/**
	 * @var int
	 */
	public $uniqueId;

	/**
	 * UNIX epoch date in seconds (UTC).  Apple will discard this message if it has not been delivered by this time.
	 *
	 * @var int
	 */
	public $expiryTime;

	/**
	 * Populated if this message failed to send.
	 *
	 * @var ZAPNSError
	 */
	public $sendError;

	/**
	 * Internal token for logging purposes
	 *
	 * @var string
	 */
	public $trackingToken;

	public static $VALID_SOUND_EXTENSIONS = array(
		'wav',
		'aiff',
		'caf'
	);

	public function __construct($apnsToken, $message = null, $badgeCount = null, $sound = null, $customLink = null, $expiryTime = 0)
	{
		$this->apnsToken    = $apnsToken;
		$this->message      = $message;
		$this->badgeCount   = $badgeCount;
		$this->sound        = $sound;
		$this->customLink   = $customLink;

		$this->expiryTime = !empty($expiryTime) ? $expiryTime : 0;

		$this->uniqueId = rand();
	}

	/**
	 * Gets a formatted Enhanced Notification representation of this message.  Ready to stick on the wire!
	 *
	 * @param bool $checkValid
	 * @return bool|string
	 */
	public function getEnhancedNotification($checkValid = false)
	{
		$payload = $this->getPayload($checkValid);

		if ($payload === false) {
			return false;
		}

		return pack("C", 1) . pack("N", $this->uniqueId) . pack("N", $this->expiryTime) . pack("n", 32) . pack('H*', $this->apnsToken) . pack("n", strlen($payload)) . $payload;
	}

	/**
	 * Gets a JSON encoded payload for this message.  This is only one part of what you want to write to the socket.
	 *
	 * @param bool $validateMessage
	 * @return bool|mixed|string
	 */
	public function getPayload($validateMessage = false)
	{
		// Check validity
		if ($validateMessage && !$this->isValid()) {
			return false;
		}

		// Prepare the payload
		$payload = array();

		if (!empty($this->message)) {
			$payload['aps']['alert'] = $this->message;
		}

		if (!empty($this->badgeCount)) {
			$payload['aps']['badge'] = $this->badgeCount;
		}

		if (!empty($this->sound)) {
			$payload['aps']['sound'] = $this->sound;
		}

		if (!empty($this->customLink)) {
			$payload['z'] = $this->customLink;
		}

		// Encode payload
		$payload = ZJSONTools::jsonEncodeWithUnicode($payload);

		// Check validity
		if ($validateMessage) {
			if (empty($payload)) {
				ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'Empty payload on ZAPNSMessage: ' . ZJSONTools::jsonEncodeWithUnicode($this));
				return false;
			}

			// Check for valid payload size
			if (strlen($payload) > self::MAXIMUM_PAYLOAD_BYTES) {
				// We have exceeded the maximum APNS payload size
				ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'Payload size exceeded on ZAPNSMessage: ' . ZJSONTools::jsonEncodeWithUnicode($this));
				return false;
			}
		}

		return $payload;
	}

	/**
	 * Validates this message object to make sure it conforms to known format restrictions
	 *
	 * @return bool
	 */
	public function isValid()
	{
		$isValid = $this->isValidToken()
			&& $this->isValidSound()
			&& !(empty($this->message) && empty($this->badgeCount));    // Must have either a message or badge

		if (!$isValid) {
			ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'APNS message failed validation: ' . ZJSONTools::jsonEncodeWithUnicode($this));
		}

		return $isValid;
	}

	/**
	 * Checks that the APNS token is valid.
	 *
	 * @return bool
	 */
	public function isValidToken()
	{
		return !empty($this->apnsToken) && ctype_xdigit($this->apnsToken);
	}

	/**
	 * Checks that a provided sound filename is a valid format.
	 *
	 * @return bool|mixed
	 */
	public function isValidSound()
	{
		if (empty($this->sound) || $this->sound == 'default') {
			return true;
		}

		$fileExtension = substr(strrchr($this->sound, '.'), 1);
		$isValid = array_search($fileExtension, self::$VALID_SOUND_EXTENSIONS);

		return $isValid;
	}
}
