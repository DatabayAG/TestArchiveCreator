<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\DI\UIServices as UIServices;
use ILIAS\HTTP\Services as HttpServices;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\Cron\Schedule\CronJobScheduleType;

/**
 * GUI for Limited Media Control
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilTestArchiveCreatorSettingsGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilTestArchiveCreatorSettingsGUI: ilAssQuestionPageGUI, ilTestEvaluationGUI, ilTestPageGUI
 */
class ilTestArchiveCreatorSettingsGUI
{
    private HttpServices $http;
    private Refinery $refinery;
    protected ilAccessHandler $access;
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected ilTabsGUI $tabs;
    protected ilToolbarGUI $toolbar;
    protected ilGlobalTemplateInterface $tpl;
    protected UIServices $ui_services;
    /** @var ilTestArchiveCreatorPlugin */
    protected ilPlugin $plugin;
    protected ilTestArchiveCreatorConfig $config;
    protected ilTestArchiveCreatorSettings $settings;
    protected ilObjTest $testObj;

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
        $this->ui_services = $DIC->ui();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();

        $this->lng->loadLanguageModule('assessment');
        $this->lng->loadLanguageModule('cron');

        $ref_id = $DIC->http()->wrapper()->query()->retrieve('ref_id', $DIC->refinery()->kindlyTo()->int());
        $this->testObj = new ilObjTest($ref_id, true);

        /** @var ilComponentFactory $factory */
        $factory = $DIC["component.factory"];
        $this->plugin = $factory->getPlugin('tarc_ui');
        $this->config = $this->plugin->getConfig();
        $this->settings = $this->plugin->getSettings($this->testObj->getId());
    }


    /**
     * Modify the export tab toolbar
     */
    public function modifyExportToolbar()
    {

        if (empty($this->toolbar->getItems())) {
            // e.g delete confirmation is shown
            return;
        }

        // hide the standard archive (not nice)
        if ($this->config->hide_standard_archive) {
            $new_items = [];
            foreach ($this->toolbar->getItems() as $tb_item) {
                $type = $tb_item['type'] ?? null;
                $component = $tb_item['component'] ?? null;

                if ($type == 'component' && $component instanceof ILIAS\UI\Component\Dropdown\Standard) {
                    $links = [];
                    foreach ($component->getItems() as $link) {
                        if (!str_contains($link->getAction(), 'test_exp_option_arc')) {
                            $links[] = $link;
                        }
                    }
                    $tb_item['component'] = $this->ui_services->factory()->dropdown()->standard($links)
                    ->withLabel($this->lng->txt("exp_export_dropdown"));
                };
                $new_items[] = $tb_item;
            }
            $this->toolbar->setItems($new_items);
        }

        $this->toolbar->addSeparator();


        // set the return target
        $this->ctrl->saveParameter($this, 'ref_id');

        $text = $this->plugin->txt('tb_archive_label') . ' ';
        if ($this->plugin->isCronPluginActive()) {
            switch ($this->settings->status) {
                case ilTestArchiveCreatorPlugin::STATUS_PLANNED:
                    $text .= sprintf($this->plugin->txt('tb_archive_planned'), isset($this->settings->schedule) ? ilDatePresentation::formatDate($this->settings->schedule) : '');
                    break;
                case ilTestArchiveCreatorPlugin::STATUS_FINISHED:
                    $text .= $this->plugin->txt('tb_archive_finished');
                    break;
                case ilTestArchiveCreatorPlugin::STATUS_INACTIVE:
                default:
                    $text .= $this->plugin->txt('tb_archive_inactive');
                    break;
            }
        } else {
            $text .= $this->plugin->txt('tb_archive_manual');
        }
        $this->toolbar->addText($text);

        if ($this->config->isPlannedCreationAllowed()) {
            $button = ilLinkButton::getInstance();
            $button->setCaption($this->lng->txt('settings'), false);
            $button->setUrl($this->getLinkTarget('editSettings'));
            $this->toolbar->addButtonInstance($button);
        }

        if ($this->config->isInstantCreationAllowed()) {
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

        if (!$this->access->checkAccess('write', '', $this->testObj->getRefId())) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            ilUtil::redirect("goto.php?target=tst_" . $this->testObj->getRefId());
        }

        if (!$this->config->isPlannedCreationAllowed()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->redirectToURL("goto.php?target=tst_" . $this->testObj->getRefId());
        }

        $this->ctrl->saveParameter($this, 'ref_id');

        $cmd = $this->ctrl->getCmd('editSettings');

        switch ($cmd) {
            case "doAutoComplete":
                $this->$cmd();
                break;

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
                    $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
                    $this->ctrl->redirectToURL("goto.php?target=tst_" . $this->testObj->getRefId());
                }
                $this->$cmd();
                break;

            default:
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
                $this->ctrl->redirectToURL("goto.php?target=tst_" . $this->testObj->getRefId());
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

        $this->ctrl->setParameterByClass('ilObjTestGUI', 'ref_id', $this->testObj->getRefId());
        $ilLocator->addRepositoryItems($this->testObj->getRefId());
        $ilLocator->addItem($this->testObj->getTitle(), $this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

        // $this->tpl->getStandardTemplate();
        //https://github.com/ILIAS-eLearning/ILIAS/commit/0c199948c24dc454f36d6dc3fca3765dfa39e5a4
        $this->tpl->loadStandardTemplate();

        $this->tpl->setLocator();
        $this->tpl->setTitle($this->testObj->getPresentationTitle());
        $this->tpl->setDescription($this->testObj->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon(0, 'big', 'tst'), $lng->txt('obj_tst'));

        return true;
    }

    /**
     * Init the settings form
     */
    protected function initSettingsForm()
    {
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
        $schedule->setMinuteStepSize(10);
        $schedule->setDate($this->settings->schedule);
        $schedule->setInfo($this->plugin->txt('schedule_info'));
        $schedule->setRequired(true);
        $st_planned->addSubItem($schedule);

        if ($this->config->support_notifications) {
            $notifications = new ilTextInputGUI($this->plugin->txt('notifications'), 'notifications');
            $notifications->setInfo($this->plugin->txt('notifications_info'));
            $notifications->setMulti(true);
            $notifications->setDataSource($this->ctrl->getLinkTarget($this, "doAutoComplete", "", true));
            $logins = $this->settings->getNotificationLogins();
            if (!empty($logins)) {
                $notifications->setValue($logins[0]);
                $notifications->setMultiValues($logins);
            }
            $st_planned->addSubItem($notifications);
        }

        if ($this->plugin->isCronPluginActive()) {
            $schedule->setInfo($schedule->getInfo() . '<br>' .$this->getCronInfo());
        } else {
            $status->setDisabled(true);
            $status->setInfo($this->plugin->txt('message_cron_plugin_inactive'));
            $schedule->setDisabled(true);
        }

        if ($this->config->support_file_prefix) {
            $prefix = new ilTextInputGUI($this->plugin->txt('file_prefix'), 'file_prefix');
            $prefix->setInfo($this->plugin->txt('file_prefix_info'));
            $prefix->setMaxLength(8);
            $prefix->setValue($this->settings->file_prefix);
            $form->addItem($prefix);
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
        // https://github.com/ILIAS-eLearning/ILIAS/commit/84424ec7abfb0fa61acf3a606754ce654f70ca61
        // $this->tpl->show();
        $this->tpl->printToStdout();
    }


    /**
     * Save the archive settings
     */
    protected function saveSettings()
    {
        $form = $this->initSettingsForm();
        $ok = $form->checkInput();
        $form->setValuesByPost();

        if ($ok) {
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

            if ($this->config->support_file_prefix) {
                if (!$this->settings->setFilePrefix($form->getInput('file_prefix'))) {
                    $form->getItemByPostVar('file_prefix')->setAlert($this->settings->getFilePrefixError());
                    $ok = false;
                }
            }

            if ($this->settings->status == ilTestArchiveCreatorPlugin::STATUS_PLANNED) {
                if ($this->config->support_notifications) {
                    if (!$this->settings->setNotificationLogins($form->getItemByPostVar('notifications')->getMultiValues())) {
                        $form->getItemByPostVar('notifications')->setAlert($this->settings->getNotificationLoginsError());
                        $ok = false;
                    }
                }
            }
        }

        if ($ok) {
            $this->settings->save();
            $this->tpl->setOnScreenMessage('success', $this->lng->txt("settings_saved"), true);
            $this->returnToExport();
        } else {
            $this->prepareOutput();
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("form_validation_errors"), false);
            $this->tpl->setContent($form->getHTML());
            $this->tpl->printToStdout();
        }
    }

    /**
     * Called async to complete entered login names
     */
    public function doAutoComplete(): void
    {
        $fields = array('login','firstname','lastname');

        $auto = new ilUserAutoComplete();
        $auto->setSearchFields($fields);
        $auto->setResultField('login');
        $auto->enableFieldSearchableCheck(true);
        $auto->setMoreLinkAvailable(true);
        $auto->setPrivacyMode(ilUserAutoComplete::PRIVACY_MODE_RESPECT_USER_SETTING);
        $auto->setLimit(ilUserAutoComplete::MAX_ENTRIES);

        $this->http->saveResponse($this->http->response()->withBody(
            Streams::ofString(
                $auto->getList($this->http->wrapper()->query()->retrieve('term',
                    $this->refinery->kindlyTo()->string()))
            )));
        $this->http->sendResponse();
    }

    /**
     * Get info about the cron job
     */
    public function getCronInfo(): string
    {
        $infos = [];

        /** @var ilTestArchiveCronJob $job */
        $job = $this->plugin->getCronPlugin()->getCronJobInstance('test_archive_cron');
        $run = $job->getLastRun();

        $infos[] = $this->lng->txt('cron_last_run') . ': ' . ($run ? ilDatePresentation::formatDate($run) : '-');

        $schedule = match ($job->getScheduleType() ?? $job->getDefaultScheduleType()) {
            CronJobScheduleType::SCHEDULE_TYPE_DAILY => $this->lng->txt('cron_schedule_daily'),
            CronJobScheduleType::SCHEDULE_TYPE_WEEKLY => $this->lng->txt('cron_schedule_weekly'),
            CronJobScheduleType::SCHEDULE_TYPE_MONTHLY => $this->lng->txt('cron_schedule_monthly'),
            CronJobScheduleType::SCHEDULE_TYPE_QUARTERLY => $this->lng->txt('cron_schedule_quarterly'),
            CronJobScheduleType::SCHEDULE_TYPE_YEARLY => $this->lng->txt('cron_schedule_yearly'),
            CronJobScheduleType::SCHEDULE_TYPE_IN_MINUTES => sprintf(
                $this->lng->txt('cron_schedule_in_minutes'),
                $job->getScheduleValue() ?? $job->getDefaultScheduleValue()
            ),
            CronJobScheduleType::SCHEDULE_TYPE_IN_HOURS => sprintf(
                $this->lng->txt('cron_schedule_in_hours'),
                $job->getScheduleValue() ?? $job->getDefaultScheduleValue()
            ),
            CronJobScheduleType::SCHEDULE_TYPE_IN_DAYS => sprintf(
                $this->lng->txt('cron_schedule_in_days'),
                $job->getScheduleValue() ?? $job->getDefaultScheduleValue()
            )
        };

        $infos[] = $this->lng->txt('cron_schedule') . ': ' . $schedule;

        return implode('<br>', $infos);
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
        if ($creator->createArchive()) {
            $this->tpl->setOnScreenMessage('success', $this->plugin->txt('archive_created'), true);
        }
        if (!empty($creator->getErrors())) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt('archive_errors')
            . '<br>' . implode('<br>', $creator->getErrors()), true);
        }
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
        $this->ctrl->redirectByClass(array('ilobjtestgui', 'iltestexportgui'));
    }
}
