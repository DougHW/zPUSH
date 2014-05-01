<?php
/**
 * Created by dougw on 2/13/14 5:52 PM
 * 
 * Copyright Zoosk, Inc. 2014
 */

/**
 * Class ZPushLogging
 *
 * The purpose of this class is to provide an easy place to connect this library's logging to whatever logging
 * system the owning project uses.
 */
class ZPushLogging
{
	const LOG_LEVEL_DEBUG   = 0;
	const LOG_LEVEL_INFO    = 1;
	const LOG_LEVEL_WARN    = 2;
	const LOG_LEVEL_ERROR   = 3;
	const LOG_LEVEL_FATAL   = 4;

	public static function Log($severity, $title, $message)
	{
		switch($severity) {
			case ZPushLogging::LOG_LEVEL_DEBUG:
				L::Debug($title, $message);
				break;
			case ZPushLogging::LOG_LEVEL_INFO:
				L::Info($title, $message);
				break;
			case ZPushLogging::LOG_LEVEL_WARN:
				L::Warn($title, $message);
				break;
			case ZPushLogging::LOG_LEVEL_ERROR:
				L::Error($title, $message);
				break;
			case ZPushLogging::LOG_LEVEL_FATAL:
				L::Fatal($title, $message);
				break;
		}
	}
}