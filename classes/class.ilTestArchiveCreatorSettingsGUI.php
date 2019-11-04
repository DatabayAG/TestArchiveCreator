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
 * @ilCtrl_Calls ilTestArchiveCreatorSettingsGUI: ilAssQuestionPageGUI, ilTestEvaluationGUI
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

	/** @var  ilTestArchiveCreatorConfig $config */
	protected $config;

	/** @var  ilTestArchiveCreatorSettings  $settings*/
	protected $settings;

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

		$this->testObj = new ilObjTest($_GET['ref_id'], true);

		$this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'TestArchiveCreator');
		$this->config = $this->plugin->getConfig();
		$this->settings = $this->plugin->getSettings($this->testObj->getId());
    }


    /**
	 * Modify the export tab toolbar
	 */
	public function modifyExportToolbar()
	{

		if (empty($this->toolbar->getItems()))
		{
			// e.g delete confirmation is shown
			return;
		}
		$this->toolbar->addSeparator();


		// hide the standard archive (not nice)
		if ($this->config->hide_standard_archive) {
			foreach ($this->toolbar->getItems() as $item) {
				/** @var ilSelectInputGUI $select */
				if ($item['input'] instanceof ilSelectInputGUI) {
					$select = $item['input'];
					if ($select->getPostVar() == 'format') {
						$options = $select->getOptions();
						unset($options['arc']);
						$select->setOptions($options);
					}
				}
			}
		}

		// set the return target
		$this->ctrl->saveParameter($this, 'ref_id');

		$text = $this->plugin->txt('tb_archive_label'). ' ';
		if ($this->plugin->checkCronPluginActive())
		{
			switch ($this->settings->status) {
				case ilTestArchiveCreatorPlugin::STATUS_PLANNED:
					$text .= sprintf($this->plugin->txt('tb_archive_planned'), ilDatePresentation::formatDate($this->settings->schedule));
					break;
				case ilTestArchiveCreatorPlugin::STATUS_FINISHED:
					$text .= $this->plugin->txt('tb_archive_finished');
					break;
				case ilTestArchiveCreatorPlugin::STATUS_INACTIVE:
				default:
					$text .= $this->plugin->txt('tb_archive_inactive');
					break;
			}
		}
		else
		{
			$text .= $this->plugin->txt('tb_archive_manual');
		}
		$this->toolbar->addText($text);

		if ($this->config->isPlannedCreationAllowed()) {
			include_once "Services/UIComponent/Button/classes/class.ilLinkButton.php";
			$button = ilLinkButton::getInstance();
			$button->setCaption($this->lng->txt('settings'), false);
			$button->setUrl($this->getLinkTarget('editSettings'));
			$this->toolbar->addButtonInstance($button);
		}

		if ($this->config->isInstantCreationAllowed()) {
			include_once "Services/UIComponent/Button/classes/class.ilLinkButton.php";
			$button = ilLinkButton::getInstance();
			$button->setCaption($this->lng->txt('create'), false);
			$button->setUrl($this->getLinkTarget('createArchive'));
			$this->toolbar->addButtonInstance($button);
		}
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

		if (!$this->config->isPlannedCreationAllowed()) {
			ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
			$this->ctrl->redirectToURL("goto.php?target=tst_".$this->testObj->getRefId());
		}

		$this->ctrl->saveParameter($this, 'ref_id');

		$cmd = $this->ctrl->getCmd('editSettings');

		switch ($cmd)
		{
			case "editSettings":
				$this->prepareOutput();
				$this->$cmd();
                break;
			case "saveSettings":
			case "cancelSettings":
				$this->$cmd();
				break;
            case "createArchive":
				if (!$this->config->isInstantCreationAllowed()) {
					ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
					$this->ctrl->redirectToURL("goto.php?target=tst_".$this->testObj->getRefId());
				}
				$this->$cmd();
				break;

			default:
                ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
                $this->ctrl->redirectToURL("goto.php?target=tst_".$this->testObj->getRefId());
				break;
		}
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
		require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'editSettings'));
		$form->setTitle($this->plugin->txt('edit_archive_settings'));


		$st_inactive = new ilRadioOption($this->plugin->txt('status_inactive'), ilTestArchiveCreatorPlugin::STATUS_INACTIVE);
		$st_planned = new ilRadioOption($this->plugin->txt('status_planned'), ilTestArchiveCreatorPlugin::STATUS_PLANNED);
		$st_finished = new ilRadioOption($this->plugin->txt('status_finished'), ilTestArchiveCreatorPlugin::STATUS_FINISHED);
		$st_finished->setDisabled(true);

		$status = new ilRadioGroupInputGUI($this->plugin->txt('status'), 'status');
		$status->addOption($st_inactive);
		$status->addOption($st_planned);
		$status->addOption($st_finished);
		$status->setValue($this->settings->status);
		$form->addItem($status);

		$schedule = new ilDateTimeInputGUI($this->plugin->txt('schedule'), 'schedule');
		$schedule->setShowTime(true);
		$schedule->setShowSeconds(false);
		$schedule->setMinuteStepSize(10);
		$schedule->setDate($this->settings->schedule);
		$schedule->setInfo($this->plugin->txt('schedule_info'));
		$st_planned->addSubItem($schedule);

		if (!$this->plugin->checkCronPluginActive()) {
			$status->setDisabled(true);
			$status->setInfo($this->plugin->txt('message_cron_plugin_inactive'));
			$schedule->setDisabled(true);
		}

        $questions = new ilCheckboxInputGUI($this->plugin->txt('include_questions'), 'include_questions');
        $questions->setInfo($this->plugin->txt('include_questions_info'));
        $questions->setChecked($this->settings->include_questions);
        $form->addItem($questions);

        if ($this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_RANDOM) {
            $random_questions = new ilSelectInputGUI($this->plugin->txt('random_questions'), 'random_questions');
            $random_questions->setOptions(array(
                ilTestArchiveCreatorPlugin::RANDOM_ALL => $this->plugin->txt('random_questions_all'),
                ilTestArchiveCreatorPlugin::RANDOM_USED => $this->plugin->txt('random_questions_used'),
            ));
            $random_questions->setValue($this->settings->random_questions);
            $questions->addSubItem($random_questions);
        }

        $qbest = new ilCheckboxInputGUI($this->plugin->txt('questions_with_best_solution'), 'questions_with_best_solution');
        $qbest->setInfo($this->plugin->txt('questions_with_best_solution_info'));
        $qbest->setChecked($this->settings->questions_with_best_solution);
        $questions->addSubItem($qbest);


        $answers = new ilCheckboxInputGUI($this->plugin->txt('include_answers'), 'include_answers');
        $answers->setInfo($this->plugin->txt('include_answers_info'));
        $answers->setChecked($this->settings->include_answers);
        $form->addItem($answers);

        $pass_selection = new ilSelectInputGUI($this->plugin->txt('pass_selection'), 'pass_selection');
        $pass_selection->setOptions(array(
            ilTestArchiveCreatorPlugin::PASS_SCORED => $this->plugin->txt('pass_scored'),
            ilTestArchiveCreatorPlugin::PASS_ALL => $this->plugin->txt('pass_all'),
        ));
        $pass_selection->setValue($this->settings->pass_selection);
        $answers->addSubItem($pass_selection);

        $abest = new ilCheckboxInputGUI($this->plugin->txt('answers_with_best_solution'), 'answers_with_best_solution');
        $abest->setInfo($this->plugin->txt('answers_with_best_solution_info'));
        $abest->setChecked($this->settings->answers_with_best_solution);
        $answers->addSubItem($abest);


        $orientation = new ilSelectInputGUI($this->plugin->txt('orientation'), 'orientation');
        $orientation->setOptions(array(
            ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT => $this->plugin->txt('orientation_portrait'),
            ilTestArchiveCreatorPlugin::ORIENTATION_LANDSCAPE => $this->plugin->txt('orientation_landscape'),
        ));
        $orientation->setValue($this->settings->orientation);
        $form->addItem($orientation);

        $zoom_factor = new ilNumberInputGUI($this->plugin->txt('zoom_factor'), 'zoom_factor');
        $zoom_factor->setSize(5);
        $zoom_factor->allowDecimals(false);
        $zoom_factor->setValue($this->settings->zoom_factor * 100);
        $form->addItem($zoom_factor);

//        $min_wait = new ilNumberInputGUI($this->plugin->txt('min_rendering_wait'), 'min_rendering_wait');
//        $min_wait->setInfo($this->plugin->txt('min_rendering_wait_info'));
//        $min_wait->setSize(5);
//        $min_wait->allowDecimals(false);
//        $min_wait->setValue($this->settings->min_rendering_wait);
//        $min_wait->setMinValue(1);
//        $form->addItem($min_wait);
//
//        $max_wait = new ilNumberInputGUI($this->plugin->txt('max_rendering_wait'), 'max_rendering_wait');
//        $max_wait->setInfo($this->plugin->txt('max_rendering_wait_info'));
//        $max_wait->setSize(5);
//        $max_wait->allowDecimals(false);
//        $max_wait->setMinValue(1);
//        $max_wait->setValue($this->settings->max_rendering_wait);
//        $form->addItem($max_wait);

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
		if (!$form->checkInput())
		{
			$form->setValuesByPost();
            $this->prepareOutput();
			$this->tpl->setContent($form->getHTML());
			$this->tpl->show();
			return;
		}
		$this->settings->status = $form->getInput('status');
		$this->settings->schedule = $form->getItemByPostVar('schedule')->getDate();

        $this->settings->include_questions = $form->getInput('include_questions');
        $this->settings->include_answers = $form->getInput('include_answers');
        $this->settings->questions_with_best_solution = $form->getInput('questions_with_best_solution');
        $this->settings->answers_with_best_solution = $form->getInput('answers_with_best_solution');

        $this->settings->pass_selection = $form->getInput('pass_selection');
        if ($this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_RANDOM) {
            $this->settings->random_questions = $form->getInput('random_questions');
        }

        $this->settings->orientation = $form->getInput('orientation');
		$this->settings->zoom_factor = $form->getInput('zoom_factor') / 100;

//        $this->settings->min_rendering_wait = $form->getInput('min_rendering_wait');
//        $this->settings->max_rendering_wait = $form->getInput('max_rendering_wait');

        $this->settings->save();

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
     * Call the archive creation
     */
    protected function createArchive()
    {
    	$creator = $this->plugin->getArchiveCreator($this->testObj->getId());
    	$creator->createArchive();
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