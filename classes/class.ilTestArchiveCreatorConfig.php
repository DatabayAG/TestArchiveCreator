<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Global Configuration for the Test Archive Creator
 */
class ilTestArchiveCreatorConfig
{
	/** @var  float path to the executable of PhantomJS */
	public $phantomjs_path;

	/** @var float zoom factor for pdf generation */
	public $zoom_factor;

	/** @var string  paper orientation of the generated pdf */
	public $orientation;

	/** @var  string  selection of the test passes to include in the archive */
	public $pass_selection;

	/** @var  string  selection of the random questions to include in the archive */
	public $random_questions;

	/** @var  bool include the user login in the pdf */
	public $with_login;

	/** @var  bool include the user matriculation number in the archive */
	public $with_matriculation;

	/** @var  bool hide the standard test archive */
	public $hide_standard_archive;

	/** @var  bool keep the creation directory */
	public $keep_creation_directory;


	/** @var ilTestArchiveCreatorPlugin $plugin */
	protected $plugin;

	/** @var ilSetting  */
	protected $settings;

	/**
	 * Constructor
	 * Initializes the configuration values
	 *
	 * @param ilTestArchiveCreatorPlugin $plugin
	 */
	public function __construct($plugin) {
		$this->plugin = $plugin;

		require_once("Services/Administration/classes/class.ilSetting.php");
		$this->settings = new ilSetting('ilTestArchiveCreator');

		$this->phantomjs_path = (string) $this->settings->get('phantomjs_path', '/opt/phantomjs/phantomjs');
		$this->hide_standard_archive = (bool) $this->settings->get('hide_standard_archive', true);
		$this->keep_creation_directory = (string) $this->settings->get('keep_creation_directory', false);

		$this->with_login = (bool) $this->settings->get('with_login', true);
		$this->with_matriculation = (bool) $this->settings->get('with_matriculation', true);

		$this->pass_selection = (string) $this->settings->get('pass_selection', ilTestArchiveCreatorPlugin::PASS_SCORED);
		$this->random_questions = (string) $this->settings->get('random_questions', ilTestArchiveCreatorPlugin::RANDOM_USED);
		$this->zoom_factor = (float) $this->settings->get('zoom_factor', '1.0');
		$this->orientation = (string) $this->settings->get('orientation', ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT);
	}


	/**
	 * Save the configuration
	 */
	public function save()
	{
		$this->settings->set('phantomjs_path', (string) $this->phantomjs_path);
		$this->settings->set('hide_standard_archive', $this->hide_standard_archive ? '1' : '0');
		$this->settings->set('keep_creation_directory', $this->keep_creation_directory ? '1' : '0');

		$this->settings->set('with_login', $this->with_login ? '1' : '0');
		$this->settings->set('with_matriculation', $this->with_matriculation ? '1' : '0');

		$this->settings->set('pass_selection', (string) $this->pass_selection);
		$this->settings->set('pass_selection', (string) $this->random_questions);
		$this->settings->set('zoom_factor', (string) $this->zoom_factor);
		$this->settings->set('orientation', (string) $this->orientation);
	}
}