<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\DI\Container;

/**
 * Basic plugin file
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilTestArchiveCreatorPlugin extends ilUserInterfaceHookPlugin
{
	const PASS_ALL = 'all';
	const PASS_SCORED = 'scored';

	const ORIENTATION_PORTRAIT = 'portrait';
	const ORIENTATION_LANDSCAPE = 'landscape';

	const STATUS_INACTIVE = 'inactive';
	const STATUS_PLANNED = 'planned';
	const STATUS_FINISHED = 'finished';
	const STATUS_RUNNING = 'running';

	const RANDOM_ALL = 'all';
	const RANDOM_USED = 'used';


	/** @var ilTestArchiveCreatorConfig */
	protected $config;


	/** @var ilTestArchiveCreatorSettings[] */
	protected $settings = [];


    public function init() : void
    {
        parent::init();
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    /**
	 * Get the plugin name
	 * @return string
	 */
	public function getPluginName() : string
	{
		return "TestArchiveCreator";
	}

	/**
	 * Get the global configuration
	 */
	public function getConfig() : ilTestArchiveCreatorConfig
	{
		if (!isset($this->config)) {
			$this->config = new ilTestArchiveCreatorConfig($this);
		}
		return $this->config;
	}


	/**
	 * Get the settings for a test object
	 */
	public function getSettings(int $obj_id) : ilTestArchiveCreatorSettings
	{
		if (!isset($this->settings[$obj_id])) {
			$this->settings[$obj_id] = new ilTestArchiveCreatorSettings($this, $obj_id);
		}
		return $this->settings[$obj_id];
	}

    /**
     * Get the working directory as relative path in the storage file system
     */
    public function getWorkdir(int $obj_id) : string
    {
        return 'tst_data/archive_plugin/tst_'. $obj_id;
    }

    /**
     * Get the url for loading assets
     */
    public function getAssetsUrl(int $obj_id) : string
    {
        return ILIAS_HTTP_PATH . '/' . $this->getDirectory() . '/assets.php/' . $obj_id;
    }


	/**
	 * Get the archive creator
	 */
	public function getArchiveCreator(int $obj_id) : ilTestArchiveCreator
	{
		return new ilTestArchiveCreator($this, $obj_id);
	}

    /**
     * Check if the test and assessment log is active
     */
    public function isTestLogActive() : bool
    {
        return ilObjAssessmentFolder::_enabledAssessmentLogging();
    }

    /**
     * Check if the examination protocol plugin is active
     */
    public function isExaminationProtocolPluginActive(): bool
    {
        return !empty($this->getActivePluginBySlotAndName('uihk', 'ExaminationProtocol'));
    }

	/**
     * Check if the player plugin is active
     */
	public function isCronPluginActive() : bool
    {
        return !empty($this->getActivePluginBySlotAndName('crnhk', 'TestArchiveCron'));
    }

    /**
     * Get the examination protocol plugin object
     */
    public function getExaminationProtocolPlugin() : ?ilPlugin
    {
        return $this->getActivePluginBySlotAndName('uihk', 'ExaminationProtocol');
    }

    /**
     * Get an active plugin by slot id and plugin name
     */
    public function getActivePluginBySlotAndName(string $slot_id, string $plugin_name) : ?ilPlugin
    {
        /** @var \ILIAS\DI\Container $DIC */
        global $DIC;

        try {
            /** @var ilComponentFactory $factory */
            $factory = $DIC["component.factory"];

            /** @var ilPlugin $plugin */
            Foreach ($factory->getActivePluginsInSlot($slot_id) as $plugin) {
                if ($plugin->getPluginName() == $plugin_name) {
                    return $plugin;
                }
            }
        }
        catch (Exception $e) {
            return null;
        }

        return null;
    }

    /**
	 * Handle a call by the cron job plugin
	 * @return	int		Number of created archives
	 * @throws	Exception
	 */
    public function handleCronJob() : int
	{
        global $DIC;

        // manual cron job execution in the admin gui
        if (ilContext::usesHTTP())
        {
           // save the current controller parameters to be restored afterwards
            $params = $DIC->http()->request()->getQueryParams();
            $ref_id = $params['ref_id'];
            $base_class = $params['baseClass'];
            $cmd_class = $params['cmdClass'];
        }

        // initialize controller for the Question GUI
        $this->initCtrl($DIC, 'ilUIPluginRouterGUI', 'ilTestArchiveCreatorSettingsGUI');

        $created = 0;
        foreach (ilTestArchiveCreatorSettings::getScheduledObjects() as $obj_id)
        {
            $creator = new ilTestArchiveCreator($this, $obj_id);
            if ($creator->createArchive()) {
                $creator->settings->status = self::STATUS_FINISHED;
                $creator->settings->save();
                $created++;
            }
            unset($creator);
        }

        // manual cron job execution in the admin gui
        if (ilContext::usesHTTP())
        {
            // restore the former controller status
            // this allows a proper redirection after the return from the job run
            $this->initCtrl($DIC, $base_class, $cmd_class);
            $DIC->ctrl()->setParameterbyClass($base_class, 'ref_id', $ref_id);
        }


        return $created;
	}

    /**
     * Initialize the controller to get working base and command classes for the question page GUI
     *
     * This is needed to allow a rendering of question page content in PRESENTATION mode
     * An alternative approach would be to render the pages in OFFLINE mode
     *
     * @see \InitCtrlService::init
     * @see \ilTestArchiveCreator::addILIASPage
     *
     * @throws ilCtrlException if the initialization fails.
     */
    public function initCtrl(Container $dic, string $base_class, string $cmd_class): void
    {
        $ilias_path = dirname(__FILE__, 9) . '/';

        try {
            $ctrl_structure = new ilCtrlStructure(
                require $ilias_path . ilCtrlStructureArtifactObjective::ARTIFACT_PATH,
                require $ilias_path . ilCtrlBaseClassArtifactObjective::ARTIFACT_PATH,
                require $ilias_path . ilCtrlSecurityArtifactObjective::ARTIFACT_PATH
            );
        } catch (Throwable $t) {
            throw new ilCtrlException(self::class . " could not require artifacts, try `composer du` first.");
        }

        $token_repository = new ilCtrlTokenRepository();
        $path_factory = new ilCtrlPathFactory($ctrl_structure);

        $own_wrapper = new \ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper([
           'baseClass' => $base_class,
           'cmdClass' => $cmd_class,
           'cmdNode' => $ctrl_structure->getClassCidByName($base_class) . ': ' . $ctrl_structure->getClassCidByName($cmd_class)
        ]);

        $context = new ilCtrlContext(
            $path_factory,
            $own_wrapper,
            $dic->refinery()
        );

        // create global instance of ilCtrl
        $GLOBALS['ilCtrl'] = new ilCtrl(
            $ctrl_structure,
            $token_repository,
            $path_factory,
            $context,
            $dic["http.response_sender_strategy"],
            $dic->http()->request(),
            $dic->http()->wrapper()->post(),
            $own_wrapper,
            $dic->refinery(),
            $dic["component.factory"]
        );

        // add helper function to DI container that
        // returns the global instance.
        // but unset the previous entry
        $dic->offsetUnset('ilCtrl');
        $dic['ilCtrl'] = static function () {
            return $GLOBALS['ilCtrl'];
        };
    }


    /**
	 * Build the exam id and allow ids without active_id and pass
	 */
	public function buildExamId(ilObjTest $testObj, ?int $active_id = null, ?int $pass = null) : string
	{
		global $DIC;
		/** @var ilSetting $ilPluginAdmin */
		$ilSetting = $DIC['ilSetting'];


		$inst_id = $ilSetting->get( 'inst_id', null );
		$obj_id = $testObj->getId();

		$examId = 'I' . $inst_id . '_T' . $obj_id;

		if (isset($active_id))
		{
			$examId .=  '_A' . $active_id;
		}

		if (isset($pass))
		{
			$examId .= '_P' . $pass;
		}

		return $examId;
	}

	/**
	 * Build a full question id like the exam id
	 * @param ilObjTest $testObj
	 * @param $question_id
	 * @return string
	 */
	public function buildExamQuestionId($testObj, $question_id) : string
	{
		return $this->buildExamId($testObj). '_Q' . $question_id;
	}

	/**
	 * Cleanup when uninstalling
	 */
	public function beforeUninstall() : bool
	{
		global $DIC;
		$ilDB = $DIC->database();
		$ilDB->dropTable('tarc_ui_settings');

		return parent::beforeUninstall();
	}


	/**
	 * Check if the user has administrative access
	 */
	public function hasAdminAccess() : bool
	{
		global $rbacsystem;
		return $rbacsystem->checkAccess("visible", SYSTEM_FOLDER_ID);
	}
}
