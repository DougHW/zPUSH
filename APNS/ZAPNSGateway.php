<?php
/**
 * Created by dougw on 10/9/13 5:06 PM
 * 
 * Copyright Zoosk, Inc. 2013
 */

class ZAPNSGateway
{
	const LOG_TAG = 'zPUSH.APNS.ZAPNSGateway';

	/**
	 * When writing to the socket, we will retry on failure until this number of total attempts have been made.
	 */
	const DEFAULT_MAXIMUM_WRITE_ATTEMPTS = 2;

	/**
	 * When polling for errors, we will poll for this many seconds total
	 */
	const ERROR_POLLING_WAIT_TIME_SECONDS = 1;
	/**
	 * We will wait this many milliseconds between each polling attempt
	 */
	const ERROR_POLLING_FREQUENCY_MILLISECONDS = 50;

	const APNS_PROD_GATEWAY_HOST = 'gateway.push.apple.com';
	const APNS_PROD_GATEWAY_PORT = 2195;
	const APNS_SANDBOX_GATEWAY_HOST = 'gateway.sandbox.push.apple.com';
	const APNS_SANDBOX_GATEWAY_PORT = 2195;

	/**
	 * Writes to this gateway will be attempted this number of times before failing completely.
	 *
	 * @var int
	 */
	public $maximumWriteAttempts = self::DEFAULT_MAXIMUM_WRITE_ATTEMPTS;
	/**
	 * Timeout for initial socket connection attempt (seconds).
	 *
	 * @var int
	 */
	public $socketTimeout = 60;

	/**
	 * Count of the number of messages successfully written to this gateway instance.
	 * Keep in mind that the gateway has no knowledge of retries or duplicates.
	 *
	 * @var int
	 */
	public $sentMessageCount = 0;

	/**
	 * Set this through the setter method to change between production and sandbox APNS modes.
	 *
	 * @var bool
	 */
	private $sandboxMode = false;

	/**
	 * File path to developer APNS certificate.
	 * @see https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW2
	 *
	 * @var string
	 */
	private $certificatePath;
	/**
	 * Passphrase for developer APNS certificate if it is protected with one.
	 *
	 * @var string
	 */
	private $certificatePassphrase;
	/**
	 * Entrust root CA certificate if needed.  If this certificate chain is already installed on your server this may be
	 * unnecessary.  Check with your System Admin if necessary.
	 * @see https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW2
	 *
	 * @var string
	 */
	private $rootCertificatePath;

	/**
	 * Stream resource to handler to the APNS service.
	 *
	 * @var resource
	 */
	protected $apnsConnection;

	protected static $singletonGateways = array();

	/**
	 * Make this public if you really want to instantiate your own gateways.  It's highly recommended to make
	 * re-use of an already open pipe here though. (see ZAPNSGateway::GetInstance()).
	 *
	 * @param $certificatePath
	 * @param null $certificatePassphrase
	 * @param null $rootCertificatePath
	 */
	protected function __construct($certificatePath, $certificatePassphrase = null, $rootCertificatePath = null)
	{
		$this->certificatePath = $certificatePath;
		$this->certificatePassphrase = $certificatePassphrase;
		$this->rootCertificatePath = $rootCertificatePath;
	}

	public function __destruct()
	{
		$this->disconnect();
	}

	/**
	 * Returns a singleton (multiton actually) instance of the an apns gateway.  If you're into that sort of thing.
	 *
	 * Multitons are keyed off the unique combination of parameters passed.  Pass the same parameters, get the same
	 * instance.  Pass different ones, get a different instance.
	 *
	 * @returns ZAPNSGateway
	 */
	public static function GetInstance($certificatePath, $certificatePassphrase = null, $rootCertificatePath = null) {
		$singletonKey = md5($certificatePath . $certificatePassphrase . $rootCertificatePath);

		if (!isset(self::$singletonGateways[$singletonKey])) {
			self::$singletonGateways[$singletonKey] = new ZAPNSGateway($certificatePath, $certificatePassphrase, $rootCertificatePath);
		}
		return self::$singletonGateways[$singletonKey];
	}

	public function sendPushNotification(ZAPNSMessage $zAPNSMessage, $validateMessage = false)
	{
		// Get the encoded message
		$encodedMessage = $zAPNSMessage->getEnhancedNotification($validateMessage);

		if (!$encodedMessage) {
			ZPushLogging::Log(
				ZPushLogging::LOG_LEVEL_DEBUG,
				self::LOG_TAG,
				'Failed to get encoded version of ZAPNSMessage: ' . ZJSONTools::jsonEncodeWithUnicode($zAPNSMessage)
			);
			return false;
		}

		// Make sure we have an open connection
		if (!$this->connect()) {
			return false;
		}

		// Write the content
		$writeResult = @fwrite($this->apnsConnection, $encodedMessage);

		// Retry as necessary
		for ($retryAttempt = 1; $writeResult < strlen($encodedMessage) && $retryAttempt < $this->maximumWriteAttempts; $retryAttempt++) {
			// Disconnect and reconnect
			$this->disconnect();
			$this->connect();

			// Write the content again
			$writeResult = @fwrite($this->apnsConnection, $encodedMessage);
		}

		if ($writeResult < strlen($encodedMessage)) {
			ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'Failed to write message to stream: ' . $encodedMessage);
			return false;
		}

		$this->sentMessageCount++;

		return true;
	}

	/**
	 * Use this to change the service to sandbox mode.  Default is production mode.
	 *
	 * @param $enabled
	 */
	public function setSandboxMode($enabled)
	{
		$needsReconnect = false;
		if ($enabled != $this->sandboxMode) {
			$needsReconnect = true;
		}

		$this->sandboxMode = $enabled;

		if ($needsReconnect) {
			$this->disconnect(); // Will reconnect automatically upon send
		}
	}

	/**
	 * Polls for a period of time to see if any errors have been written to the socket.
	 *
	 * WARNING - This function will take a long time by design!
	 *
	 * @return ZAPNSError|null
	 */
	public function pollForErrors()
	{
		// Loop for a second checking for errors
		$startTime = microtime(true);
		while(microtime(true) - $startTime < self::ERROR_POLLING_WAIT_TIME_SECONDS) {
			usleep(self::ERROR_POLLING_FREQUENCY_MILLISECONDS * 1000);

			$apnsError = $this->getErrors();
			if (!empty($apnsError)) {
				return $apnsError;
			}
		}

		return null;
	}

	/**
	 * Returns an ZAPNSError object if the socket has an error waiting to be read.
	 *
	 * @return ZAPNSError|null
	 */
	public function getErrors()
	{
		if (empty($this->apnsConnection)) {
			return null;
		}

		stream_set_blocking($this->apnsConnection, 0);
		$apple_error_response = @fread($this->apnsConnection, 6);
		if ($apple_error_response) {
			$error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response); //unpack the error response (first byte 'command" should always be 8)

			return new ZAPNSError($error_response['status_code'], $error_response['identifier']);
		}

		return null;
	}

	/**
	 * Make a connection to the APNS gateway
	 *
	 * @return bool     True if connection was successful, false otherwise.
	 */
	public function connect()
	{
		if (!empty($this->apnsConnection)) {
			return true;
		}

		$streamContext = stream_context_create();

		// Set up SSL context parameters
		stream_context_set_option($streamContext, 'ssl', 'local_cert', $this->certificatePath);
		if (!empty($this->certificatePassphrase)) {
			stream_context_set_option($streamContext, 'ssl', 'passphrase', $this->passPhrase); // Certificate file pass phrase
		}
		if (!empty($this->rootCertificatePath)) {
			stream_context_set_option($streamContext, 'ssl', 'cafile', $this->rootCertificatePath);
			stream_context_set_option($streamContext, 'ssl', 'verify_peer', true);
		}

		// Create the URL
		if (!$this->sandboxMode) {
			// Prod mode
			$apnsURL = 'ssl://' . self::APNS_PROD_GATEWAY_HOST . ':' . self::APNS_PROD_GATEWAY_PORT;
		} else {
			$apnsURL = 'ssl://' . self::APNS_SANDBOX_GATEWAY_HOST . ':' . self::APNS_SANDBOX_GATEWAY_PORT;
		}

		// Create open socket stream
		$this->apnsConnection = @stream_socket_client(
			$apnsURL,
			$error,
			$errorString,
			$this->socketTimeout,
			STREAM_CLIENT_CONNECT,
			$streamContext
		);

		// Check for failure
		if($this->apnsConnection == false) {
			ZPushLogging::Log(
				ZPushLogging::LOG_LEVEL_ERROR,
				self::LOG_TAG,
				'Failed to connect to ' . $apnsURL . ' Error: ' . $error . ' ' .  $errorString
			);
			return false;
		}

		return true;
	}

	/**
	 * Disconnects from the APNS gateway
	 *
	 * @return bool
	 */
	public function disconnect()
	{
		$closeSuccess = @fclose($this->apnsConnection);

		if (!$closeSuccess) {
			return false;
		}

		$this->apnsConnection = null;

		return true;
	}
}
