<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UICore\GlobalTemplate;
use ILIAS\Cron\Job\Schedule\JobScheduleType;
use ILIAS\Filesystem\Stream\Streams;

/**
 * GUI for Test Archive Creator Settings
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilTestArchiveCreatorSettingsGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilTestArchiveCreatorSettingsGUI: ilAssQuestionPageGUI, ilTestEvaluationGUI, ilTestPageGUI
 */
class ilTestArchiveCreatorSettingsGUI
{
    private HTTPServices $http;
    private Refinery $refinery;
    private Factory $ui_factory;
    private Renderer $ui_renderer;
    private ilLocatorGUI $locator;
    private ilAccessHandler $access;
    private ilObjUser $user;
    private ilCtrl $ctrl;
    private ilLanguage $lng;
    private ilToolbarGUI $toolbar;
    private ilGlobalTemplateInterface $tpl;

    /** @var ilTestArchiveCreatorPlugin */
    private ilPlugin $plugin;
    private ilTestArchiveCreatorConfig $config;
    private ilTestArchiveCreatorSettings $settings;
    private ilObjTest $testObj;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->access = $DIC->access();
        $this->user = $DIC->user();
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->toolbar = $DIC->toolbar();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->locator = $DIC["ilLocator"];

        $this->lng->loadLanguageModule('assessment');
        $this->lng->loadLanguageModule('cron');

        $ref_id = $this->http->wrapper()->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
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
                    $tb_item['component'] = $this->ui_factory->dropdown()->standard($links)
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
        if ($this->testObj->getAnonymity()) {
            $text .= $this->plugin->txt('tb_archive_not_for_anonymized');
        } elseif ($this->plugin->isCronJobActive()) {
            switch ($this->settings->status) {
                case ilTestArchiveCreatorPlugin::STATUS_PLANNED:
                    $text .= sprintf($this->plugin->txt('tb_archive_planned'), isset($this->settings->schedule) ?
                        ilDatePresentation::formatDate(new ilDateTime($this->settings->schedule->getTimestamp(), IL_CAL_UNIX)) : '');
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
            $button->setDisabled($this->testObj->getAnonymity());
            $this->toolbar->addButtonInstance($button);
        }

        if ($this->config->isInstantCreationAllowed()) {
            $button = ilLinkButton::getInstance();
            $button->setCaption($this->lng->txt('create'), false);
            $button->setUrl($this->getLinkTarget('createArchive'));
            $button->setDisabled($this->testObj->getAnonymity());
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

        if ($this->testObj->getAnonymity()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("permission_denied"), true);
            $this->ctrl->redirectToURL("goto.php?target=tst_" . $this->testObj->getRefId());
        }

        $this->ctrl->saveParameter($this, 'ref_id');

        $cmd = $this->ctrl->getCmd('editSettings');

        switch ($cmd) {

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
        $this->ctrl->setParameterByClass('ilObjTestGUI', 'ref_id', $this->testObj->getRefId());
        $this->locator->addRepositoryItems($this->testObj->getRefId());
        $this->locator->addItem($this->testObj->getTitle(), $this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

        // $this->tpl->getStandardTemplate();
        //https://github.com/ILIAS-eLearning/ILIAS/commit/0c199948c24dc454f36d6dc3fca3765dfa39e5a4
        $this->tpl->loadStandardTemplate();

        $this->tpl->setLocator();
        $this->tpl->setTitle($this->testObj->getPresentationTitle());
        $this->tpl->setDescription($this->testObj->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon(0, 'big', 'tst'), $this->lng->txt('obj_tst'));

        return true;
    }

    /**
     * Init the settings form
     */
    protected function initSettingsForm(): Standard
    {
        $f = $this->ui_factory->input()->field();

        $planned_group_inputs = [
            'schedule' => $f->dateTime(
                $this->plugin->txt('schedule'),
                $this->plugin->txt('schedule_info')
                . ($this->plugin->isCronJobActive() ? '<br>' . $this->getCronInfo() : '')
            )->withUseTime(true)
              ->withRequired(true)
             ->withValue($this->settings->schedule?->setTimezone(new DateTimeZone($this->user->getTimeZone()))),
        ];

        if ($this->config->support_notifications) {
            $planned_group_inputs['notifications'] = $f->text($this->plugin->txt('notifications'), $this->plugin->txt('notifications_info'))
            ->withValue($this->settings->getNotificationLogins())
                ->withAdditionalTransformation(
                    $this->refinery->custom()->constraint(
                        $this->settings->checkNotificationLogins(...),
                        $this->settings->getNotificationLoginsError(...)
                    )
                );
        }

        $inputs['status_group'] = $f->switchableGroup(
            [
                ilTestArchiveCreatorPlugin::STATUS_INACTIVE => $f->group([])
                    ->withLabel($this->plugin->txt('status_inactive')),
                ilTestArchiveCreatorPlugin::STATUS_PLANNED => $f->group($planned_group_inputs)
                    ->withLabel($this->plugin->txt('status_planned'))
                    ->withDisabled(!$this->plugin->isCronJobActive())
                    ->withByline(!$this->plugin->isCronJobActive() ? $this->plugin->txt('message_cron_job_inactive') : '')
            ],
            $this->plugin->txt('status')
        )->withValue($this->settings->selectedStatus());

        if ($this->config->support_file_prefix) {
            $inputs['file_prefix'] = $f->text($this->plugin->txt('file_prefix'), $this->plugin->txt('file_prefix_info'))
               ->withMaxLength(8)
               ->withValue($this->settings->file_prefix)
                ->withAdditionalTransformation(
                    $this->refinery->custom()->constraint(
                        $this->settings->checkFilePrefix(...),
                        $this->plugin->txt("wrong_file_prefix")
                    )
                );
        }

        $question_group_inputs = [];
        $question_group_values = [];
        if ($this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_RANDOM) {
            $question_group_inputs['random_questions'] = $f->radio($this->plugin->txt('random_questions'), )
                                                           ->withOption(ilTestArchiveCreatorPlugin::RANDOM_ALL, $this->plugin->txt('random_questions_all'))
                                                           ->withOption(ilTestArchiveCreatorPlugin::RANDOM_USED, $this->plugin->txt('random_questions_used'))
                                                           ->withValue($this->settings->random_questions);
            $question_group_values['random_questions'] = $this->settings->random_questions;
        }
        $question_group_inputs['questions_with_best_solution'] = $f->checkbox(
            $this->plugin->txt('questions_with_best_solution'),
            $this->plugin->txt('questions_with_best_solution_info')
        )->withValue($this->settings->questions_with_best_solution);
        $question_group_values['questions_with_best_solution'] = $this->settings->questions_with_best_solution;

        $inputs['include_questions'] = $f->optionalGroup(
            $question_group_inputs,
            $this->plugin->txt('include_questions'),
            $this->plugin->txt('include_questions_info')
        )->withValue($this->settings->include_questions ? $question_group_values : null);

        $answer_group_inputs = [
            'pass_selection' => $f->radio(
                $this->plugin->txt('pass_selection')
            )->withOption(ilTestArchiveCreatorPlugin::PASS_SCORED, $this->plugin->txt('pass_scored'))
              ->withOption(ilTestArchiveCreatorPlugin::PASS_ALL, $this->plugin->txt('pass_all'))
              ->withValue($this->settings->pass_selection),
            'answers_with_best_solution' => $f->checkbox(
                $this->plugin->txt('answers_with_best_solution'),
                $this->plugin->txt('answers_with_best_solution_info')
            )->withValue($this->settings->answers_with_best_solution)
        ];
        $answer_group_values = [
            'pass_selection' => $this->settings->pass_selection,
            'answers_with_best_solution' => $this->settings->answers_with_best_solution
        ];

        $inputs['include_answers'] = $f->optionalGroup(
            $answer_group_inputs,
            $this->plugin->txt('include_answers'),
            $this->plugin->txt('include_answers_info')
        )->withValue($this->settings->include_answers ? $answer_group_values : null);

        $inputs['orientation'] = $f->radio($this->plugin->txt('orientation'))
          ->withOption(ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT, $this->plugin->txt('orientation_portrait'))
          ->withOption(ilTestArchiveCreatorPlugin::ORIENTATION_LANDSCAPE, $this->plugin->txt('orientation_landscape'))
          ->withValue($this->settings->orientation);

        $inputs['zoom_factor'] = $f->numeric($this->plugin->txt('zoom_factor'))
                                   ->withValue((int) ($this->settings->zoom_factor * 100));

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'saveSettings'),
            $inputs
        );
    }


    /**
     * Edit the archive settings
     */
    protected function editSettings(): void
    {
        $form = $this->initSettingsForm();
        $this->tpl->setContent($this->ui_renderer->render($form));
        $this->tpl->printToStdout();
    }


    /**
     * Save the archive settings
     */
    protected function saveSettings(): void
    {
        $form = $this->initSettingsForm();
        $request = $this->http->request();
        $form = $form->withRequest($request);
        $data = $form->getData();

        if (!$data) {
            $this->prepareOutput();
            $this->tpl->setOnScreenMessage(GlobalTemplate::MESSAGE_TYPE_FAILURE, $this->plugin->txt("form_validation_errors"), false);
            $this->tpl->setContent($this->ui_renderer->render($form));
            $this->tpl->printToStdout();
            return;
        }

        $this->settings->status = $data['status_group'][0];
        if ($this->settings->status == ilTestArchiveCreatorPlugin::STATUS_PLANNED) {
            $this->settings->schedule = $data['status_group'][1]['schedule'];
            if ($this->config->support_notifications) {
                $this->settings->setNotificationLogins($data['status_group'][1]['notifications']);
            }
        }

        $this->settings->include_questions = (bool) $data['include_questions'];
        if ($this->settings->include_questions) {
            if ($this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_RANDOM) {
                $this->settings->random_questions = $data['include_questions']['random_questions'];
            }
            $this->settings->questions_with_best_solution = $data['include_questions']['questions_with_best_solution'];
        }

        $this->settings->include_answers = (bool) $data['include_answers'];
        if ($this->settings->include_answers) {
            $this->settings->pass_selection = $data['include_answers']['pass_selection'];
            $this->settings->answers_with_best_solution = $data['include_answers']['answers_with_best_solution'];
        }

        $this->settings->orientation = $data['orientation'];
        $this->settings->zoom_factor = $data['zoom_factor'] / 100;
        $this->settings->file_prefix = $data['file_prefix'];

        $this->settings->save();
        $this->tpl->setOnScreenMessage(GlobalTemplate::MESSAGE_TYPE_SUCCESS, $this->lng->txt("settings_saved"), true);
        $this->returnToExport();
    }


    /**
     * Get info about the cron job
     */
    public function getCronInfo(): string
    {
        $infos = [];

        /** @var ilTestArchiveCreatorCronJob $job */
        $job = $this->plugin->getCronJobInstance(ilTestArchiveCreatorCronJob::id);
        $run = $job->getLastRun();

        $infos[] = $this->lng->txt('cron_last_run') . ': ' . ($run ? ilDatePresentation::formatDate($run) : '-');

        if ($job->isActive()) {
            $schedule = match ($job->getScheduleType() ?? $job->getDefaultScheduleType()) {
                JobScheduleType::DAILY => $this->lng->txt('cron_schedule_daily'),
                JobScheduleType::WEEKLY => $this->lng->txt('cron_schedule_weekly'),
                JobScheduleType::MONTHLY => $this->lng->txt('cron_schedule_monthly'),
                JobScheduleType::QUARTERLY => $this->lng->txt('cron_schedule_quarterly'),
                JobScheduleType::YEARLY => $this->lng->txt('cron_schedule_yearly'),
                JobScheduleType::IN_MINUTES => sprintf(
                    $this->lng->txt('cron_schedule_in_minutes'),
                    $job->getScheduleValue() ?? $job->getDefaultScheduleValue()
                ),
                JobScheduleType::IN_HOURS => sprintf(
                    $this->lng->txt('cron_schedule_in_hours'),
                    $job->getScheduleValue() ?? $job->getDefaultScheduleValue()
                ),
                JobScheduleType::IN_DAYS => sprintf(
                    $this->lng->txt('cron_schedule_in_days'),
                    $job->getScheduleValue() ?? $job->getDefaultScheduleValue()
                )
            };

            $infos[] = $this->lng->txt('cron_schedule') . ': ' . $schedule;
        } else {
            $infos[] = $this->plugin->txt('cron_job_not_active');
        }

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
