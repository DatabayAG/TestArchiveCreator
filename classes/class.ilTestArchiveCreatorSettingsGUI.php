<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

require_once('./Modules/Test/classes/class.ilObjTest.php');

/**
 * GUI for Limited Media Control
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilTestArchiveCreatorSettingsGUI: ilUIPluginRouterGUI
 */
class ilTestArchiveCreatorSettingsGUI
{
	/** @var  ilAccessHandler $access */
	protected $access;

	/** @var ilCtrl $ctrl */
	protected $ctrl;

	/** @var  ilLanguage $lng */
	protected $lng;

	/** @var ilTabsGUI */
	protected $tabs;

	/** @var  ilToolbarGUI $toolbar */
	protected $toolbar;

	/** @var ilTemplate $tpl */
	protected $tpl;

	/** @var ilTestArchiveCreatorPlugin $plugin */
	protected $plugin;

	/** @var ilObjTest $testObj */
	protected $testObj;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $DIC;

		$this->access = $DIC->access();
		$this->ctrl = $DIC->ctrl();
		$this->lng = $DIC->language();
		$this->tabs = $DIC->tabs();
		$this->toolbar = $DIC->toolbar();
		$this->tpl = $DIC['tpl'];

		$this->lng->loadLanguageModule('assessment');

		$this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'TestArchiveCreator');

		$this->testObj = new ilObjTest($_GET['ref_id'], true);
    }


    /**
	 * Modify the export tab toolbar
	 */
	public function modifyExportToolbar()
	{
		$this->toolbar->addSeparator();

		// set the return target
		$this->ctrl->saveParameter($this, 'ref_id');
		//$this->ctrl->setParameter($this, 'return', urlencode($_SERVER['SCRIPT_NAME'].'?'.$_SERVER['QUERY_STRING']));

		include_once "Services/UIComponent/Button/classes/class.ilLinkButton.php";
		$button = ilLinkButton::getInstance();
		$button->setCaption($this->lng->txt('settings'), false);
		$button->setUrl($this->getLinkTarget('editSettings'));
		$this->toolbar->addButtonInstance($button);
	}


	/**
	* Handles all commands, default is "show"
	*/
	public function executeCommand()
	{

		if (!$this->access->checkAccess('write','',$this->testObj->getRefId()))
		{
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
		}

		$this->ctrl->saveParameter($this, 'ref_id');

		$cmd = $this->ctrl->getCmd('editSettings');

		switch ($cmd)
		{
			case "editSettings":
				if ($this->prepareOutput())
				{
					$this->$cmd();
				}
                break;
			case "saveSettings":
			case "cancelSettings":
            case "createArchive":
				$this->$cmd();
				break;

			default:
                ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
                ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
				break;
		}
	}

	/**
	 * Get the plugin object
	 * @return ilUILimitedMediaControlPlugin|null
	 */
	public function getPlugin()
	{
		return $this->plugin;
	}


    /**
	 * Prepare the test header, tabs etc.
	 */
	protected function prepareOutput()
	{
		/** @var ilLocatorGUI $ilLocator */
		/** @var ilLanguage $lng */
		global $ilLocator, $lng;

		$this->ctrl->setParameterByClass('ilObjTestGUI', 'ref_id',  $this->testObj->getRefId());
		$ilLocator->addRepositoryItems($this->testObj->getRefId());
		$ilLocator->addItem($this->testObj->getTitle(),$this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

		$this->tpl->getStandardTemplate();
		$this->tpl->setLocator();
		$this->tpl->setTitle($this->testObj->getPresentationTitle());
		$this->tpl->setDescription($this->testObj->getLongDescription());
		$this->tpl->setTitleIcon(ilObject::_getIcon('', 'big', 'tst'), $lng->txt('obj_tst'));

		return true;
	}

	/**
	 * Init the settings form
	 */
	protected function initSettingsForm()
	{
		$settings = $this->plugin->getSettings($this->testObj->getId());

		require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'editSettings'));
		$form->setTitle($this->plugin->txt('edit_archive_settings'));

		$status = new ilRadioGroupInputGUI($this->plugin->txt('status'), 'status');
		$status->setValue($settings->status);
		$form->addItem($status);

		$st_inactive = new ilRadioOption($this->plugin->txt('status_inactive'), ilTestArchiveCreatorSettings::STATUS_INACTIVE);
		$st_planned = new ilRadioOption($this->plugin->txt('status_planned'), ilTestArchiveCreatorSettings::STATUS_PLANNED);
		$st_finished = new ilRadioOption($this->plugin->txt('status_finished'), ilTestArchiveCreatorSettings::STATUS_FINISHED);
		$st_finished->setDisabled(true);

		$status->addOption($st_inactive);
		$status->addOption($st_planned);
		$status->addOption($st_finished);

		$schedule = new ilDateTimeInputGUI($this->plugin->txt('schedule'), 'schedule');
		$schedule->setShowTime(true);
		$schedule->setShowSeconds(false);
		$schedule->setMinuteStepSize(5);
		$schedule->setDate($settings->schedule);
		$st_planned->addSubItem($schedule);

		$pass_selection = new ilSelectInputGUI($this->plugin->txt('pass_selection'));
		$pass_selection->setValue($settings->pass_selection);
		$pass_selection->setOptions(array(
			ilTestArchiveCreatorSettings::PASS_SCORED, $this->plugin->txt('pass_scored'),
			ilTestArchiveCreatorSettings::PASS_BEST, $this->plugin->txt('pass_best'),
			ilTestArchiveCreatorSettings::PASS_LAST, $this->plugin->txt('pass_last'),
			ilTestArchiveCreatorSettings::PASS_ALL, $this->plugin->txt('pass_all'),
		));
		$form->addItem($pass_selection);

		$form->addCommandButton('saveSettings', $this->lng->txt('save'));
		$form->addCommandButton('cancelSettings', $this->lng->txt('cancel'));

		return $form;
	}


    /**
     * Edit the archive settings
     */
    protected function editSettings()
    {
		$form = $this->initSettingsForm();
        $this->tpl->setContent($form->getHTML());
        $this->tpl->show();
    }

    /**
     * Save the archive settings
     */
    protected function saveSettings()
    {
		$form = $this->initSettingsForm();
		$form->setValuesByPost();
		if (!$form->checkInput())
		{
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHTML());
			$this->tpl->show();
		}

		$settings = $this->plugin->getSettings($this->testObj->getId());
		$settings->status = $form->getInput('status');
		$settings->pass_selection = $form->getInput('pass_selection');
		$settings->schedule = $form->getItemByPostVar('schedule')->getDate();
		$settings->save();

        ilUtil::sendSuccess($this->plugin->txt('settings_saved'), true);
		$this->returnToExport();
    }


	/**
	 * Cancel the archive settings
	 */
	protected function cancelSettings()
	{
		$this->returnToExport();
	}


	/**
     * Call the archive cration
     */
    protected function createArchive()
    {
		$this->returnToExport();
    }


	/**
	 * Get the link target for a command using the ui plugin router
	 * @param string $a_cmd
	 * @return string
	 */
	protected function getLinkTarget($a_cmd = '')
	{
		return $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', get_class($this)), $a_cmd);
	}


	protected function returnToExport()
	{
		$this->ctrl->setParameterByClass('ilTestExportGUI', 'ref_id', $this->testObj->getRefId());
		$this->ctrl->redirectByClass(array('ilObjTestGUI', 'ilTestExportGUI'));
	}
}
?>