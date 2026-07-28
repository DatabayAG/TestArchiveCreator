<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\DI\Container;
use ILIAS\Cron\CronJob;
use ILIAS\Cron\Job\JobProvider;
use ILIAS\Plugin\TestArchiveCreator\DBUpdateSteps11;

/**
 * Basic plugin file
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilTestArchiveCreatorPlugin extends ilUserInterfaceHookPlugin implements JobProvider
{
    private const PATH_IN_PUBLIC = 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TestArchiveCreator';
    private const LANG_MODULE = 'ui_uihk_tarc_ui';

    public const PASS_ALL = 'all';
    public const PASS_SCORED = 'scored';

    public const ORIENTATION_PORTRAIT = 'portrait';
    public const ORIENTATION_LANDSCAPE = 'landscape';

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_RUNNING = 'running';

    public const RANDOM_ALL = 'all';
    public const RANDOM_USED = 'used';


    /** @var ilTestArchiveCreatorConfig */
    protected $config;


    /** @var ilTestArchiveCreatorSettings[] */
    protected $settings = [];

    protected ?CronJob $cron_job = null;

    public function install(): void
    {
        global $DIC;
        parent::install();
        (new DBUpdateSteps11())->install($DIC->database());
    }

    public function uninstall(): bool
    {
        global $DIC;
        parent::uninstall();
        (new DBUpdateSteps11())->uninstall($DIC->database());
        return true;
    }

    /**
     * Get the plugin name
     * @return string
     */
    public function getPluginName(): string
    {
        return "TestArchiveCreator";
    }

    /**
     * Get the global configuration
     */
    public function getConfig(): ilTestArchiveCreatorConfig
    {
        if (!isset($this->config)) {
            $this->config = new ilTestArchiveCreatorConfig($this);
        }
        return $this->config;
    }

    /**
     * Get the plugin path in the public directory
     */
    public function getPathInPublic(): string
    {
        return self::PATH_IN_PUBLIC;
    }

    /**
     * Get the settings for a test object
     */
    public function getSettings(int $obj_id): ilTestArchiveCreatorSettings
    {
        if (!isset($this->settings[$obj_id])) {
            $this->settings[$obj_id] = new ilTestArchiveCreatorSettings($this, $obj_id);
        }
        return $this->settings[$obj_id];
    }


    /**
     * Get the url for loading assets
     */
    public function getAssetsUrl(int $obj_id, string $temp_id): string
    {
        return ILIAS_HTTP_PATH . '/' . self::PATH_IN_PUBLIC . '/assets.php/' . $obj_id . '/' . $temp_id;
    }

    /**
     * Get the module for loading templates
     */
    public function getModuleForTemplates(): string
    {
        return 'public/' . self::PATH_IN_PUBLIC;
    }

    /**
     * Get a template of the plugin
     */
    public function getTemplate(string $a_template, bool $a_par1 = true, bool $a_par2 = true): ilTemplate
    {
        return new ilTemplate($a_template, $a_par1, $a_par2, self::getModuleForTemplates());
    }

    /**
     * Get the archive creator
     */
    public function getArchiveCreator(int $obj_id): ilTestArchiveCreator
    {
        return new ilTestArchiveCreator($this, $obj_id);
    }

    /**
     * Check if the test and assessment log is active
     */
    public function isTestLogActive(): bool
    {
        /** @var \ILIAS\Test\Settings\GlobalSettings\Repository $repository */
        $repository = \ILIAS\Test\TestDIC::dic()['settings.global.repository'];
        return $repository->getLoggingSettings()->isLoggingEnabled();
    }

    /**
     * Check if the examination protocol plugin is active
     */
    public function isExaminationProtocolPluginActive(): bool
    {
        return !empty($this->getActivePluginBySlotAndName('uihk', 'ExaminationProtocol'));
    }

    /**
     * Check if the cron plugin is active
     */
    public function isCronJobActive(): bool
    {
        return $this->getCronJobInstance(ilTestArchiveCreatorCronJob::id)->isActive();
    }

    /**
     * Get the examination protocol plugin object
     */
    public function getExaminationProtocolPlugin(): ?ilPlugin
    {
        return $this->getActivePluginBySlotAndName('uihk', 'ExaminationProtocol');
    }


    /**
     * Get an active plugin by slot id and plugin name
     */
    public function getActivePluginBySlotAndName(string $slot_id, string $plugin_name): ?ilPlugin
    {
        /** @var \ILIAS\DI\Container $DIC */
        global $DIC;

        try {
            /** @var ilComponentFactory $factory */
            $factory = $DIC["component.factory"];

            /** @var ilPlugin $plugin */
            foreach ($factory->getActivePluginsInSlot($slot_id) as $plugin) {
                if ($plugin->getPluginName() == $plugin_name) {
                    return $plugin;
                }
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Handle a call by the cron job plugin
     * @return	int		Number of created archives
     * @throws	Exception
     */
    public function handleCronJob(): int
    {
        global $DIC;

        /** @var ILIAS\StaticURL\Services $static_url */
        $static_url = $DIC['static_url'];
        $access = $DIC->access();
        $notifier = new ilTestArchiveCreatorNotification($this, new ilMail(ANONYMOUS_USER_ID));

        // manual cron job execution in the admin gui
        if (ilContext::usesHTTP()) {
            // save the current controller parameters to be restored afterwards
            $params = $DIC->http()->request()->getQueryParams();
            $ref_id = $params['ref_id'];
            $base_class = $params['baseClass'];
            $cmd_class = $params['cmdClass'];
        }

        // initialize controller for the Question GUI
        $this->initCtrl($DIC, 'ilUIPluginRouterGUI', 'ilTestArchiveCreatorSettingsGUI');

        $created = 0;
        foreach (ilTestArchiveCreatorSettings::getScheduledObjects() as $obj_id) {
            $creator = new ilTestArchiveCreator($this, $obj_id);
            if ($creator->createArchive()) {
                if ($this->config->support_notifications) {
                    $ref_ids = ilObject::_getAllReferences($obj_id);
                    $title = ilObject::_lookupTitle($obj_id);

                    foreach ($creator->settings->notification_ids as $user_id) {
                        $link = '';
                        foreach ($ref_ids as $ref_id) {
                            if ($access->checkAccessOfUser($user_id, 'read', '', $ref_id)) {
                                $link = $static_url->builder()->build('tst', new \ILIAS\Data\ReferenceId($ref_id));
                                break;
                            }
                        }
                        $notifier->addNotification($user_id, $link . ' ' . $title);
                    }
                }

                $creator->settings->status = self::STATUS_FINISHED;
                $creator->settings->save();
                $created++;
            }
            unset($creator);
        }
        $notifier->sendNotifications();

        // manual cron job execution in the admin gui
        if (ilContext::usesHTTP()) {
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
                require  ilCtrlStructureArtifactObjective::PATH(),
                require  ilCtrlBaseClassArtifactObjective::PATH(),
                require  ilCtrlSecurityArtifactObjective::PATH()
            );
        } catch (Throwable $t) {
            throw new ilCtrlException(self::class . " could not require artifacts, try `composer du` first.");
        }

        $token_repository = new ilCtrlTokenRepository();
        $path_factory = new ilCtrlPathFactory($ctrl_structure);

        $own_wrapper = new \ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper([
           'baseClass' => $base_class,
           'cmdClass' => $cmd_class,
           'cmdNode' => $ctrl_structure->getClassCidByName($base_class) . ':' . $ctrl_structure->getClassCidByName($cmd_class)
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
            $dic["component.factory"],
            new ilCtrlSubject(),
            new ilCtrlQueryRegexParser()
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
    public function buildExamId(ilObjTest $testObj, ?int $active_id = null, ?int $pass = null): string
    {
        global $DIC;
        /** @var ilSetting $ilPluginAdmin */
        $ilSetting = $DIC['ilSetting'];


        $inst_id = $ilSetting->get('inst_id', null);
        $obj_id = $testObj->getId();

        $examId = 'I' . $inst_id . '_T' . $obj_id;

        if (isset($active_id)) {
            $examId .= '_A' . $active_id;
        }

        if (isset($pass)) {
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
    public function buildExamQuestionId($testObj, $question_id): string
    {
        return $this->buildExamId($testObj) . '_Q' . $question_id;
    }

    /**
     * Cleanup when uninstalling
     */
    public function beforeUninstall(): bool
    {
        global $DIC;
        $ilDB = $DIC->database();
        $ilDB->dropTable('tarc_ui_settings');

        return parent::beforeUninstall();
    }


    /**
     * Check if the current user has administrative access
     */
    public function hasAdminAccess(): bool
    {
        global $DIC;
        return $DIC->rbac()->system()->checkAccess("visible", SYSTEM_FOLDER_ID);
    }

    /**
     * Check if the current user has a global role
     */
    public function hasGlobalRole(int $role_id): bool
    {
        global $DIC;
        return $DIC->rbac()->review()->isAssigned($DIC->user()->getId(), $role_id);
    }

    /**
     * Get a plugin text and use the variable, if not translated, take the current language
     * @param string $a_var
     * @param ?string $a_lang_code
     * @return string
     */
    public function txt(string $a_var, ?string $a_lang_code = null): string
    {
        global $DIC;

        if ($a_lang_code !== null) {
            $txt = $DIC->language()->txtlng(self::LANG_MODULE, self::LANG_MODULE . "_" . $a_var, $a_lang_code);
            if (substr($txt, 1, strlen(self::LANG_MODULE)) !== self::LANG_MODULE) {
                return $txt;
            }
        }
        return parent::txt($a_var);
    }

    public function getCronJobInstances(): array
    {
        return [$this->getCronJobInstance(ilTestArchiveCreatorCronJob::id)];
    }

    public function getCronJobInstance(string $jobId): CronJob
    {
        if ($jobId !== ilTestArchiveCreatorCronJob::id) {
            throw new OutOfBoundsException(
                "Job [$jobId] not found."
            );
        }

        if (!isset($this->cron_job)) {
            $this->cron_job = new ilTestArchiveCreatorCronJob($this);
            $this->cron_job->loadData();
        }
        return $this->cron_job;
    }
}
