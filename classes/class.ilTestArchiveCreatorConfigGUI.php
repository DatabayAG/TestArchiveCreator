<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

include_once("./Services/Component/classes/class.ilPluginConfigGUI.php");

/**
 * Test archive creator configuration user interface class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @author Jesus Copado <jesus.copado@fau.de>
 */
class ilTestArchiveCreatorConfigGUI extends ilPluginConfigGUI
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
	}


	/**
	 * Handles all commands, default is "configure"
	 */
	public function performCommand($cmd)
	{
		$this->plugin = $this->getPluginObject();
		$this->config = $this->plugin->getConfig();

		switch ($cmd)
		{
			case "saveConfiguration":
				$this->saveConfiguration();
				break;

			case "configure":
			default:
				$this->editConfiguration();
				break;
		}
	}

	/**
	 * Edit the configuration
	 */
	protected function editConfiguration()
	{
		$form = $this->initConfigForm();
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * Save the edited configuration
	 */
	protected function saveConfiguration()
	{
		$form = $this->initConfigForm();
		$form->setValuesByPost();
		if (!$form->checkInput())
		{
			$form->setValuesByPost();
			$this->tpl->setContent($form->getHTML());
			$this->tpl->show();
		}

		$this->config->phantomjs_path = $form->getInput('phantomjs_path');
		$this->config->hide_standard_archive = $form->getInput('hide_standard_archive');
		$this->config->keep_creation_directory = $form->getInput('keep_creation_directory');
		$this->config->use_system_styles = $form->getInput('use_system_styles');
		$this->config->any_ssl_protocol = $form->getInput('any_ssl_protocol');
		$this->config->ignore_ssl_errors = $form->getInput('ignore_ssl_errors');
		$this->config->answers_with_best_solution = $form->getInput('answers_with_best_solution');
		$this->config->with_login = $form->getInput('with_login');
		$this->config->with_matriculation = $form->getInput('with_matriculation');

		$this->config->pass_selection = $form->getInput('pass_selection');
		$this->config->orientation = $form->getInput('orientation');
		$this->config->zoom_factor = $form->getInput('zoom_factor') / 100;
		$this->config->save();


		ilUtil::sendSuccess($this->plugin->txt('settings_saved'), true);
		$this->ctrl->redirect($this, 'editConfiguration');
	}

	/**
	 * Fill the configuration form
	 * @return ilPropertyFormGUI
	 */
	protected function initConfigForm()
	{
		require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'editConfiguration'));
		$form->setTitle($this->plugin->txt('plugin_configuration'));

		$path = new ilTextInputGUI($this->plugin->txt('phantomjs_path'), 'phantomjs_path');
		$path->setInfo($this->plugin->txt('phantomjs_path_info'));
		$path->setValue($this->config->phantomjs_path);
		$form->addItem($path);

		$hide = new ilCheckboxInputGUI($this->plugin->txt('hide_standard_archive'), 'hide_standard_archive');
		$hide->setInfo($this->plugin->txt('hide_standard_archive_info'));
		$hide->setChecked($this->config->hide_standard_archive);
		$form->addItem($hide);

		$keep = new ilCheckboxInputGUI($this->plugin->txt('keep_creation_directory'), 'keep_creation_directory');
		$keep->setInfo($this->plugin->txt('keep_creation_directory_info'));
		$keep->setChecked($this->config->keep_creation_directory);
		$form->addItem($keep);

		$styles = new ilCheckboxInputGUI($this->plugin->txt('use_system_styles'), 'use_system_styles');
		$styles->setInfo($this->plugin->txt('use_system_styles_info'));
		$styles->setChecked($this->config->use_system_styles);
		$form->addItem($styles);

		$protocol = new ilCheckboxInputGUI($this->plugin->txt('any_ssl_protocol'), 'any_ssl_protocol');
		$protocol->setInfo($this->plugin->txt('any_ssl_protocol_info'));
		$protocol->setChecked($this->config->any_ssl_protocol);
		$form->addItem($protocol);

		$errors = new ilCheckboxInputGUI($this->plugin->txt('ignore_ssl_errors'), 'ignore_ssl_errors');
		$errors->setInfo($this->plugin->txt('ignore_ssl_errors_info'));
		$errors->setChecked($this->config->ignore_ssl_errors);
		$form->addItem($errors);

		$best = new ilCheckboxInputGUI($this->plugin->txt('answers_with_best_solution'), 'answers_with_best_solution');
		$best->setInfo($this->plugin->txt('answers_with_best_solution_info'));
		$best->setChecked($this->config->answers_with_best_solution);
		$form->addItem($best);

		$header = new ilFormSectionHeaderGUI();
		$header->setTitle($this->plugin->txt('privacy_settings'));
		$form->addItem($header);

		$with_login = new ilCheckboxInputGUI($this->plugin->txt('with_login'), 'with_login');
		$with_login->setInfo($this->plugin->txt('with_login_info'));
		$with_login->setChecked($this->config->with_login);
		$form->addItem($with_login);

		$with_matriculation = new ilCheckboxInputGUI($this->plugin->txt('with_matriculation'), 'with_matriculation');
		$with_matriculation->setInfo($this->plugin->txt('with_matriculation_info'));
		$with_matriculation->setChecked($this->config->with_matriculation);
		$form->addItem($with_matriculation);

		$header = new ilFormSectionHeaderGUI();
		$header->setTitle($this->plugin->txt('object_defaults'));
		$form->addItem($header);

		$pass_selection = new ilSelectInputGUI($this->plugin->txt('pass_selection'), 'pass_selection');
		$pass_selection->setOptions(array(
			ilTestArchiveCreatorPlugin::PASS_SCORED => $this->plugin->txt('pass_scored'),
			ilTestArchiveCreatorPlugin::PASS_ALL => $this->plugin->txt('pass_all'),
		));
		$pass_selection->setValue($this->config->pass_selection);
		$form->addItem($pass_selection);

		$random_questions = new ilSelectInputGUI($this->plugin->txt('random_questions'), 'random_questions');
		$random_questions->setOptions(array(
			ilTestArchiveCreatorPlugin::RANDOM_ALL => $this->plugin->txt('random_questions_all'),
			ilTestArchiveCreatorPlugin::RANDOM_USED => $this->plugin->txt('random_questions_used'),
		));
		$random_questions->setValue($this->config->random_questions);
		$form->addItem($random_questions);

		$orientation = new ilSelectInputGUI($this->plugin->txt('orientation'), 'orientation');
		$orientation->setOptions(array(
			ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT => $this->plugin->txt('orientation_portrait'),
			ilTestArchiveCreatorPlugin::ORIENTATION_LANDSCAPE => $this->plugin->txt('orientation_landscape'),
		));
		$orientation->setValue($this->config->orientation);
		$form->addItem($orientation);

		$zoom_factor = new ilNumberInputGUI($this->plugin->txt('zoom_factor'), 'zoom_factor');
		$zoom_factor->setSize(5);
		$zoom_factor->allowDecimals(false);
		$zoom_factor->setValue($this->config->zoom_factor * 100);
		$form->addItem($zoom_factor);

		$form->addCommandButton('saveConfiguration', $this->lng->txt('save'));

		return $form;

	}
}