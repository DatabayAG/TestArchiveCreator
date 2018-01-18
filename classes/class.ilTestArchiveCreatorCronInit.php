<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

class ilTestArchiveCreatorCronInit extends ilInitialisation
{
	static $savedCtrl;
	static $savedGet;

	/**
	 * Initialize GUI and controller when called from cron job
	 * (needed by assQuestionGUI)
	 */
	public static function initCronCall()
	{
		// todo: find a better way
		self::$savedCtrl = $GLOBALS['ilCtrl'];
		self::$savedGet = $_GET;

		$ctrl = new ilCtrl;
		$GLOBALS['ilCtrl'] = $ctrl;

		if(!ilContext::hasHTML()) {
			require_once "./Services/UICore/classes/class.ilTemplate.php";
			self::initHTML();
		}

		$ctrl->initBaseClass('ilUIPluginRouterGUI');
		$ctrl->setCmdClass('iltestarchivecreatorsettingsgui');
	}

	/**
	 * Re-Initialize the original controller parameters
	 * (needed when called manually from the list of cron jobs)
	 */
	public static function exitCronCall()
	{
		$GLOBALS['ilCtrl'] = self::$savedCtrl;
		$_GET = self::$savedGet;
	}
}