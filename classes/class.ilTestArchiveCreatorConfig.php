<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Global Configuration for the Test Archive Creator
 */
class ilTestArchiveCreatorConfig
{
	const ALLOW_ANY = 'any';
	const ALLOW_PLANNED = 'planned';
	const ALLOW_NONE = 'none';

    const ENGINE_NONE = '';
    const ENGINE_PHANTOM = 'phantom';
    const ENGINE_BROWSERSHOT = 'browsershot';


	/** @var string actions allowed for a standard user with write permissions on a test */
	public $user_allow;

    /** @var string */
    public $pdf_engine;

	/** @var  string path to the executable of PhantomJS */
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

    /** @var  bool keep the jobfile */
    public $keep_jobfile;

    /** @var bool embed the asset files */
    public $embed_assets;

	/** @var  bool use the system styles */
	public $use_system_styles;

	/** @var bool allow any ssl protocol */
	public $any_ssl_protocol;

	/** @var bool ignore ssl errors */
	public $ignore_ssl_errors;

	/** @var bool minimum seconds to wait for rendering */
	public $min_rendering_wait;

	/** @var bool minimum seconds to wait for rendering */
	public $max_rendering_wait;

	/** @var bool render twice */
	public $render_twice;

    /** @var string browsershot node module path */
    public $bs_node_module_path;

    /** @var string browsershot chrome binary path */
    public $bs_chrome_path;

    /** @var string browsershot node binary path */
    public $bs_node_path;

    /** @var string browsershot npm binary path */
    public $bs_npm_path;


    /** @var bool use file urls */
	public $use_file_urls;

	/** @var bool include questions */
	public $include_questions;

    /** @var bool include answers */
    public $include_answers;

    /** @var bool questions_with_best_solution */
    public $questions_with_best_solution;

    /** @var bool answers_with_best_solution */
	public $answers_with_best_solution;

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

		$this->settings = new ilSetting('ilTestArchiveCreator');

        $this->embed_assets = (bool) $this->settings->get('embed_assets', false);
		$this->user_allow = (string) $this->settings->get('user_allow', self::ALLOW_ANY);
        $this->pdf_engine = (string) $this->settings->get('pdf_engine', self::ENGINE_NONE);

        $this->phantomjs_path = (string) $this->settings->get('phantomjs_path', '/opt/phantomjs/phantomjs');
		$this->hide_standard_archive = (bool) $this->settings->get('hide_standard_archive', true);
		$this->keep_creation_directory = (bool) $this->settings->get('keep_creation_directory', false);
        $this->keep_jobfile = (bool) $this->settings->get('keep_jobfile', false);
		$this->use_system_styles = (bool) $this->settings->get('use_system_styles', true);
		$this->any_ssl_protocol = (bool)  $this->settings->get('any_ssl_protocol', false);
		$this->ignore_ssl_errors = (bool)  $this->settings->get('ignore_ssl_errors', false);
        $this->render_twice = (bool)  $this->settings->get('render_twice', false);
        $this->use_file_urls = (bool)  $this->settings->get('use_file_urls', false);

        $this->bs_node_module_path = (string) $this->settings->get('bs_node_module_path', '/home/www-data/node_modules/');
        $this->bs_chrome_path = (string) $this->settings->get('bs_chrome_path', '/home/www-data/.cache/puppeteer/chrome/linux-1108766/chrome-linux/chrome');
        $this->bs_node_path = (string) $this->settings->get('bs_node_path', '/usr/bin/node');
        $this->bs_npm_path = (string) $this->settings->get('bs_npm_path', '/usr/bin/npm');

		$this->with_login = (bool) $this->settings->get('with_login', true);
		$this->with_matriculation = (bool) $this->settings->get('with_matriculation', true);

        $this->include_questions = (bool) $this->settings->get('include_questions', true);
        $this->include_answers = (bool) $this->settings->get('include_answers', true);
        $this->questions_with_best_solution = (bool) $this->settings->get('questions_with_best_solution', true);
        $this->answers_with_best_solution = (bool) $this->settings->get('answers_with_best_solution', true);

        $this->pass_selection = (string) $this->settings->get('pass_selection', ilTestArchiveCreatorPlugin::PASS_SCORED);
		$this->random_questions = (string) $this->settings->get('random_questions', ilTestArchiveCreatorPlugin::RANDOM_USED);

		$this->zoom_factor = (float) $this->settings->get('zoom_factor', '1.0');
		$this->orientation = (string) $this->settings->get('orientation', ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT);

        $this->min_rendering_wait = (int)  $this->settings->get('min_rendering_wait', 200);
        $this->max_rendering_wait = (int)  $this->settings->get('max_rendering_wait', 2000);
    }


	/**
	 * Save the configuration
	 */
	public function save()
	{
		$this->settings->set('user_allow', (string) $this->user_allow);
        $this->settings->set('embed_assets', (bool) $this->embed_assets ? '1' : '0');
        $this->settings->set('pdf_engine', (string) $this->pdf_engine);
		$this->settings->set('phantomjs_path', (string) $this->phantomjs_path);
		$this->settings->set('hide_standard_archive', (bool) $this->hide_standard_archive ? '1' : '0');
		$this->settings->set('keep_creation_directory', (bool) $this->keep_creation_directory ? '1' : '0');
        $this->settings->set('keep_jobfile', (bool) $this->keep_jobfile ? '1' : '0');
		$this->settings->set('use_system_styles', (bool) $this->use_system_styles ? '1' : '0');
		$this->settings->set('any_ssl_protocol', (bool) $this->any_ssl_protocol ? '1' : '0');
		$this->settings->set('ignore_ssl_errors', (bool) $this->ignore_ssl_errors ? '1' : '0');
		$this->settings->set('render_twice', (bool) $this->render_twice ? '1' : '0');
        $this->settings->set('use_file_urls', (bool) $this->use_file_urls ? '1' : '0');

        $this->settings->set('bs_node_module_path', (string) $this->bs_node_module_path);
        $this->settings->set('bs_chrome_path', (string) $this->bs_chrome_path);
        $this->settings->set('bs_node_path', (string) $this->bs_node_path);
        $this->settings->set('bs_npm_path', (string) $this->bs_npm_path);

		$this->settings->set('with_login', (bool) $this->with_login ? '1' : '0');
		$this->settings->set('with_matriculation', (bool) $this->with_matriculation ? '1' : '0');

        $this->settings->set('include_questions', (bool) $this->include_questions ? '1' : '0');
        $this->settings->set('include_answers', (bool) $this->include_answers ? '1' : '0');
        $this->settings->set('questions_with_best_solution', (bool) $this->questions_with_best_solution ? '1' : '0');
        $this->settings->set('answers_with_best_solution', (bool) $this->answers_with_best_solution ? '1' : '0');

        $this->settings->set('pass_selection', (string) $this->pass_selection);
		$this->settings->set('random_questions', (string) $this->random_questions);

		$this->settings->set('zoom_factor', (string) $this->zoom_factor);
		$this->settings->set('orientation', (string) $this->orientation);

		$this->settings->set('min_rendering_wait', $this->min_rendering_wait ? (int) $this->min_rendering_wait : 1);
        $this->settings->set('max_rendering_wait', $this->max_rendering_wait ? (int) $this->max_rendering_wait : 1);
    }


	/**
	 * Is the planned creation of archives allowed or the current user
	 * @return bool
	 */
    public function isPlannedCreationAllowed()
	{
		if ($this->plugin->hasAdminAccess()) {
			return true;
		}

		return ($this->user_allow == self::ALLOW_ANY  || $this->user_allow == self::ALLOW_PLANNED);
	}

	/**
	 * Is the instant creation of archives allowed or the current user
	 * @return bool
	 */
	public function isInstantCreationAllowed()
	{
		if ($this->plugin->hasAdminAccess()) {
			return true;
		}

		return ($this->user_allow == self::ALLOW_ANY);
	}

}