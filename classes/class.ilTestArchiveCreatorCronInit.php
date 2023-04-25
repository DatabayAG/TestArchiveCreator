<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

class ilTestArchiveCreatorCronInit extends ilInitialisation
{
	static $manualCron = false;
	static $savedGet = array();
	static $savedCtrl = null;

	/**
	 * Initialize GUI and controller when called from cron job
	 * (needed by assQuestionGUI)
	 */
	public static function initCronCall()
	{
		global $DIC;
		$ctrl = $DIC->ctrl();

		if ($ctrl->getCmdClass() == 'ilcronmanagergui')
		{
			self::$manualCron = true;
			self::$savedGet = $_GET;
			self::$savedCtrl = clone $ctrl;
		}

		if(!ilContext::hasHTML()) {
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
		if (self::$manualCron) {
			$_GET = self::$savedGet;
			$GLOBALS['ilCtrl'] = self::$savedCtrl;
		}
	}
}