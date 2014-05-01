<?php
/**
 * Created by dougw on 10/7/13 11:09 AM
 * 
 * Copyright Zoosk, Inc. 2013
 */

class ZAPNSQueue
{
	const LOG_TAG = 'zPUSH.APNS.ZAPNSQueue';

	/**
	 * @var ZAPNSGateway
	 */
	protected $apnsGateway;

	/**
	 * @var array
	 */
	protected $messageQueue;

	/**
	 * @var array
	 */
	protected $messagesWithErrors;

	/**
	 * @var bool
	 */
	protected $hasRun;

	/**
	 * @var bool
	 */
	protected $validateMessages;

	/**
	 * @param ZAPNSGateway $apnsGateway     ZAPNSGateway. Does not need to be connected.
	 * @param $messageQueue                 Array of ZAPNSMessages
	 * @param $validateMessages             If true, each message will be validated for correctness before sending.
	 *                                      This is highly recommended, but will affect throughput in high volume
	 *                                      situations, and so is provided as an option.
	 */
	public function __construct(ZAPNSGateway $apnsGateway, array $messageQueue, $validateMessages = true)
	{
		$this->apnsGateway      = $apnsGateway;
		$this->messageQueue     = $messageQueue;
		$this->validateMessages = $validateMessages;
	}

	/**
	 * Will process and send all messages in this queue.
	 *
	 * This will only work once per queue instance. If you need to re-process for some reason, create a new queue.
	 *
	 * @return bool     Returns false if sending suffered unrecoverable errors.  True otherwise.
	 */
	public function run()
	{
		if ($this->hasRun()) {
			return false;
		}

		$this->messagesWithErrors = array();
		$success = $this->processMessageQueue($this->messageQueue);
		$this->hasRun = true;

		return $success;
	}

	/**
	 * Returns an array of message unique ids that experienced an error during sending.
	 * Those message objects can be fetched through getMessageQueue() and will have attached errors.
	 *
	 * @return array
	 */
	public function getMessageIdsWithErrors()
	{
		return $this->messagesWithErrors;
	}

	/**
	 * Returns true if this queue has been processed (successfully or not).
	 *
	 * @return bool
	 */
	public function hasRun()
	{
		return $this->hasRun;
	}

	/**
	 * Returns the messages array this queue was created with.
	 *
	 * @return array
	 */
	public function getMessageQueue()
	{
		return $this->messageQueue;
	}

	/**
	 * Processes and sends the message queue.
	 *
	 * @return bool     Returns false if sending suffered unrecoverable errors.  True otherwise.
	 */
	protected function processMessageQueue()
	{
		$apnsQueueKeys = array_keys($this->messageQueue);

		// Loop while we still have messages to process
		while (!empty($apnsQueueKeys)) {

			// Send each message in the queue
			foreach ($apnsQueueKeys as $apnsQueueKey) {
				// Get the associated message
				$apnsMessage = $this->messageQueue[$apnsQueueKey];

				// Send the message
				$sendSuccess = $this->apnsGateway->sendPushNotification($apnsMessage, $this->validateMessages);

				// Handle failure
				if (!$sendSuccess) {
					// Record this error
					$apnsMessage->sendError = new ZAPNSError(
						ZAPNSError::STATUS_SOCKET_ERROR,
						$apnsMessage->uniqueId
					);
					$this->messagesWithErrors[] = $apnsMessage->uniqueId;
					ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'APNS message socket level failure: ' . ZJSONTools::jsonEncodeWithUnicode($apnsMessage));

					// Check for error responses from Apple and adjust queue appropriately
					$newQueueKeys = $this->checkForErrorsAndAdjustQueue($apnsQueueKeys);

					// Close the socket in any case to be safe
					$this->apnsGateway->disconnect();

					// Check for unrecoverable errors
					if ($newQueueKeys === true) {
						ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'Unrecoverable error in APNS message queue!');
						return false;
					}

					// If we got an adjusted queue, process from that point
					if (is_array($newQueueKeys)) {
						$apnsQueueKeys = $newQueueKeys;
						continue 2; // This will continue from the outer while loop
					}
				} else {
					//success, track it
					global $LOGGER;
					Tracking::RecordSend($LOGGER, $apnsMessage->trackingToken);
				}
			}

			// We made it through the queue successfully.  Do one last check for errors since there may be a delay.
			$newQueueKeys = $this->checkForErrorsAndAdjustQueue($apnsQueueKeys);

			// If we got an adjusted queue, process from that point
			if ($newQueueKeys === false) {
				// We're clear
				return true;
			} else if ($newQueueKeys === true) {
				// There was an unrecoverable error waiting. Freak out and panic.
				// Close the socket
				$this->apnsGateway->disconnect();

				ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'Unrecoverable error in APNS message queue!');
				return false;
			} else if (is_array($newQueueKeys)) {
				// There was an error waiting, let's continue processing
				// Close the socket
				$this->apnsGateway->disconnect();

				// Set the keys to be reprocessed
				$apnsQueueKeys = $newQueueKeys;
			}
		}

		return true;
	}

	/**
	 * Checks a gateway for errors and, if present, returns an array of keys starting from the first
	 * message after the one that failed.
	 *
	 * Returns false if no error is present.  Returns true if unrecoverable errors are detected.
	 *
	 * WARNING - This function will take ERROR_CHECKING_WAIT_TIME_SECONDS to run
	 *
	 * @param $apnsGateway
	 * @param $apnsMessages
	 * @return array|bool
	 */
	protected function checkForErrorsAndAdjustQueue($apnsQueueKeys)
	{
		$apnsError = $this->apnsGateway->pollForErrors();

		// No errors?  Great, return.
		if (empty($apnsError)) {
			return false;
		}

		// There was an error, get the corresponding message
		$culpritMessage = array_key_exists($apnsError->uniqueId, $this->messageQueue) ? $this->messageQueue[$apnsError->uniqueId] : null;
		if (!$culpritMessage) {
			// Something has gone very wrong, return unrecoverable error!
			return true;
		}
		$culpritMessage->sendError = $apnsError;
		$this->messagesWithErrors[] = $culpritMessage->uniqueId;
		ZPushLogging::Log(ZPushLogging::LOG_LEVEL_DEBUG, self::LOG_TAG, 'APNS message send failure: ' . ZJSONTools::jsonEncodeWithUnicode($culpritMessage));

		// Calculate the point to replay from
		$replayQueueKeys = array();
		$pastTheError = false;

		// Collect messages to replay
		foreach($apnsQueueKeys as $apnsQueueKey) {
			if ($pastTheError) {
				// Only add the message if we are past the error
				$replayQueueKeys[] = $apnsQueueKey;
			}

			if ($this->messageQueue[$apnsQueueKey]->uniqueId == $apnsError->uniqueId) {
				$pastTheError = true;
			}
		}

		return $replayQueueKeys;
	}
}