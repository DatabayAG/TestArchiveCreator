<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\DI\RBACServices as RBACServices;
use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\UICore\GlobalTemplate;

/**
 * Test archive creator configuration user interface class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @author Jesus Copado <jesus.copado@fau.de>
 *
 *  @ilCtrl_IsCalledBy ilTestArchiveCreatorConfigGUI: ilObjComponentSettingsGUI
 */
class ilTestArchiveCreatorConfigGUI extends ilPluginConfigGUI
{
    protected ilAccessHandler $access;
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected ilTabsGUI $tabs;
    protected ilToolbarGUI $toolbar;
    protected ilGlobalTemplateInterface $tpl;
    private RBACServices $rbac;
    private Factory $ui_factory;
    private Renderer $ui_renderer;
    private HTTPServices $http;

    /** @var ilTestArchiveCreatorPlugin */
    protected ilPlugin $plugin;
    protected ilTestArchiveCreatorConfig $config;

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
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->rbac = $DIC->rbac();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();

        $this->lng->loadLanguageModule('assessment');
    }


    /**
     * Handles all commands, default is "configure"
     */
    public function performCommand(string $cmd): void
    {
        $this->plugin = $this->getPluginObject();
        $this->config = $this->plugin->getConfig();

        switch ($cmd) {
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
    protected function editConfiguration(): void
    {
        $form = $this->initConfigForm();
        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    /**
     * Save the edited configuration
     */
    protected function saveConfiguration(): void
    {
        $form = $this->initConfigForm();
        $request = $this->http->request();
        $form = $form->withRequest($request);
        $all_data = $form->getData();

        if (!$all_data) {
            $this->tpl->setContent($this->ui_renderer->render($form));
            return;
        }

        // without section
        $data = $all_data;

        $this->config->hide_standard_archive = (bool) ($data['hide_standard_archive'] ?? null);

        $this->config->keep_creation_directory = (bool) ($data['keep_creation_directory'] ?? null);
        if ($this->config->keep_creation_directory) {
            $this->config->keep_jobfile = (bool) ($data['keep_creation_directory']['keep_jobfile'] ?? null);
        }

        $this->config->support_file_prefix = (bool) ($data['support_file_prefix'] ?? null);
        $this->config->support_notifications = (bool) ($data['support_notifications'] ?? null);

        // section generation settings
        $data = $all_data['generation_settings'] ?? [];

        $this->config->embed_assets = (bool) ($data['embed_assets'] ?? null);

        $this->config->pdf_engine = (string) ($data['pdf_engine_group'][0] ?? null);
        if ($this->config->pdf_engine === ilTestArchiveCreatorConfig::ENGINE_LOCAL) {
            $this->config->bs_node_module_path = (string) ($data['pdf_engine_group'][1]['bs_node_module_path'] ?? null);
            $this->config->bs_chrome_path = (string) ($data['pdf_engine_group'][1]['bs_chrome_path'] ?? null);
            $this->config->bs_node_path = (string) ($data['pdf_engine_group'][1]['bs_node_path'] ?? null);
        } elseif ($this->config->pdf_engine === ilTestArchiveCreatorConfig::ENGINE_SERVER) {
            $this->config->server_url = (string) ($data['pdf_engine_group'][1]['server_url'] ?? null);
        }

        $this->config->ignore_ssl_errors = (bool) ($data['ignore_ssl_errors'] ?? null);

        // section object defaults
        $data = $all_data['object_defaults'] ?? [];

        $this->config->include_questions = (bool) ($data['include_questions'] ?? null);
        if ($this->config->include_questions) {
            $this->config->random_questions = (string) ($data['include_questions']['random_questions'] ?? null);
            $this->config->questions_with_best_solution = (bool) ($data['include_questions']['questions_with_best_solution'] ?? null);
        }

        $this->config->include_answers = (bool) ($data['include_answers'] ?? null);
        if ($this->config->include_answers) {
            $this->config->pass_selection = (string) ($data['include_answers']['pass_selection'] ?? null);
            $this->config->answers_with_best_solution = (bool) ($data['include_answers']['answers_with_best_solution'] ?? null);
        }

        $this->config->orientation = (string) ($data['orientation'] ?? null);
        $this->config->zoom_factor = (float) ($data['zoom_factor'] ?? 100) / 100;

        // section privacy settings
        $data = $all_data['privacy_settings'] ?? [];

        $this->config->with_login = (bool) ($data['with_login'] ?? null);
        $this->config->with_matriculation = (bool) ($data['with_matriculation'] ?? null);
        $this->config->with_results = (bool) ($data['with_results'] ?? null);
        $this->config->include_ip_ranges = (bool) ($data['include_ip_ranges'] ?? null);

        if ($this->plugin->isTestLogActive()) {
            $this->config->include_test_log = (bool) ($data['include_test_log'] ?? null);
        }
        if ($this->plugin->isExaminationProtocolPluginActive()) {
            $this->config->include_examination_protocol = (bool) ($data['include_examination_protocol'] ?? null);
        }

        // section permissions
        $data = $all_data['permissions'] ?? [];

        $this->config->require_global_role = (bool) ($data['require_role'][0] ?? null);
        if ($this->config->require_global_role) {
            $this->config->global_role_ids = array_map('intval', (array) ($data['require_role'][1]['role_select'] ?? []));
        } else {
            $this->config->global_role_ids = [];
        }

        $this->config->user_allow = (string) ($data['user_allow'] ?? null);

        $this->config->save();
        $this->tpl->setOnScreenMessage(GlobalTemplate::MESSAGE_TYPE_SUCCESS, $this->lng->txt("settings_saved"), true);
        $this->ctrl->redirect($this, 'editConfiguration');
    }

    /**
     * Fill the configuration form
     */
    protected function initConfigForm(): Standard
    {
        $f = $this->ui_factory->input()->field();

        $inputs = [
            'hide_standard_archive' => $f->checkbox(
                $this->plugin->txt('hide_standard_archive'),
                $this->plugin->txt('hide_standard_archive_info')
            )->withValue($this->config->hide_standard_archive),

            'keep_creation_directory' => $f->optionalGroup(
                [
                    'keep_jobfile' => $f->checkbox(
                        $this->plugin->txt('keep_jobfile'),
                        $this->plugin->txt('keep_jobfile_info')
                    )->withValue($this->config->keep_jobfile)
                ],
                $this->plugin->txt('keep_creation_directory'),
                $this->plugin->txt('keep_creation_directory_info')
            )->withValue($this->config->keep_creation_directory ? ['keep_jobfile' => $this->config->keep_jobfile] : null),

            'support_file_prefix' => $f->checkbox(
                $this->plugin->txt('support_file_prefix'),
                $this->plugin->txt('support_file_prefix_info')
            )->withValue($this->config->support_file_prefix),

            'support_notifications' => $f->checkbox(
                $this->plugin->txt('support_notifications'),
                $this->plugin->txt('support_notifications_info')
            )->withValue($this->config->support_notifications),

            'generation_settings' => $f->section(
                [
                    'embed_assets' => $f->checkbox(
                        $this->plugin->txt('embed_assets'),
                        $this->plugin->txt('embed_assets_info')
                    )->withValue($this->config->embed_assets),

                    'pdf_engine_group' => $f->switchableGroup(
                        [
                            ilTestArchiveCreatorConfig::ENGINE_NONE => $f->group(
                                []
                            )->withLabel($this->plugin->txt('pdf_engine_none'))->withByline($this->plugin->txt('pdf_engine_none_info')),

                            ilTestArchiveCreatorConfig::ENGINE_LOCAL => $f->group(
                                [
                                    'bs_node_module_path' => $f->text(
                                        $this->plugin->txt('bs_node_module_path'),
                                        $this->plugin->txt('bs_node_module_path_info')
                                    )->withValue($this->config->bs_node_module_path),

                                    'bs_chrome_path' => $f->text(
                                        $this->plugin->txt('bs_chrome_path'),
                                        $this->plugin->txt('bs_chrome_path_info')
                                    )->withValue($this->config->bs_chrome_path),

                                    'bs_node_path' => $f->text(
                                        $this->plugin->txt('bs_node_path'),
                                        $this->plugin->txt('bs_node_path_info')
                                    )->withValue($this->config->bs_node_path)
                                ]
                            )->withLabel($this->plugin->txt('pdf_engine_local'))->withByline($this->plugin->txt('pdf_engine_local_info')),

                            ilTestArchiveCreatorConfig::ENGINE_SERVER => $f->group(
                                [
                                    'server_url' => $f->text(
                                        $this->plugin->txt('server_url'),
                                        $this->plugin->txt('server_url_info')
                                    )->withValue($this->config->server_url)
                                ]
                            )->withLabel($this->plugin->txt('pdf_engine_server'))->withByline($this->plugin->txt('pdf_engine_server_info'))
                        ],
                        $this->plugin->txt('pdf_engine')
                    )->withValue($this->config->pdf_engine),

                    'ignore_ssl_errors' => $f->checkbox(
                        $this->plugin->txt('ignore_ssl_errors'),
                        $this->plugin->txt('ignore_ssl_errors_info')
                    )->withValue($this->config->ignore_ssl_errors)
                ],
                $this->plugin->txt('generation_settings')
            ),

            'object_defaults' => $f->section(
                [
                    'include_questions' => $f->optionalGroup(
                        [
                            'random_questions' => $f->radio($this->plugin->txt('random_questions'), )
                                ->withOption(ilTestArchiveCreatorPlugin::RANDOM_ALL, $this->plugin->txt('random_questions_all'))
                                ->withOption(ilTestArchiveCreatorPlugin::RANDOM_USED, $this->plugin->txt('random_questions_used'))
                                                    ->withValue($this->config->random_questions),

                            'questions_with_best_solution' => $f->checkbox(
                                $this->plugin->txt('questions_with_best_solution'),
                                $this->plugin->txt('questions_with_best_solution_info')
                            )->withValue($this->config->questions_with_best_solution)
                        ],
                        $this->plugin->txt('include_questions'),
                        $this->plugin->txt('include_questions_info')
                    )->withValue($this->config->include_questions ? [
                        'random_questions' => $this->config->random_questions,
                        'questions_with_best_solution' => $this->config->questions_with_best_solution
                    ] : null),

                    'include_answers' => $f->optionalGroup(
                        [
                            'pass_selection' => $f->radio(
                                $this->plugin->txt('pass_selection')
                            )
                                ->withOption(ilTestArchiveCreatorPlugin::PASS_SCORED, $this->plugin->txt('pass_scored'))
                                ->withOption(ilTestArchiveCreatorPlugin::PASS_ALL, $this->plugin->txt('pass_all'))
                                ->withValue($this->config->pass_selection),

                            'answers_with_best_solution' => $f->checkbox(
                                $this->plugin->txt('answers_with_best_solution'),
                                $this->plugin->txt('answers_with_best_solution_info')
                            )->withValue($this->config->answers_with_best_solution)
                        ],
                        $this->plugin->txt('include_answers'),
                        $this->plugin->txt('include_answers_info')
                    )->withValue($this->config->include_answers ? [
                        'pass_selection' => $this->config->pass_selection,
                        'answers_with_best_solution' => $this->config->answers_with_best_solution
                    ] : null),

                    'orientation' => $f->radio($this->plugin->txt('orientation'))
                        ->withOption(ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT, $this->plugin->txt('orientation_portrait'))
                        ->withOption(ilTestArchiveCreatorPlugin::ORIENTATION_LANDSCAPE, $this->plugin->txt('orientation_landscape'))
                        ->withValue(empty($this->config->orientation) ? ilTestArchiveCreatorPlugin::ORIENTATION_PORTRAIT : $this->config->orientation),

                    'zoom_factor' => $f->numeric(
                        $this->plugin->txt('zoom_factor')
                    )->withValue((int) ($this->config->zoom_factor * 100))
                ],
                $this->plugin->txt('object_defaults')
            ),

            'privacy_settings' => $f->section(
                [
                    'with_login' => $f->checkbox(
                        $this->plugin->txt('with_login'),
                        $this->plugin->txt('with_login_info')
                    )->withValue($this->config->with_login),

                    'with_matriculation' => $f->checkbox(
                        $this->plugin->txt('with_matriculation'),
                        $this->plugin->txt('with_matriculation_info')
                    )->withValue($this->config->with_matriculation),

                    'with_results' => $f->checkbox(
                        $this->plugin->txt('with_results'),
                        $this->plugin->txt('with_results_info')
                    )->withValue($this->config->with_results),

                    'include_ip_ranges' => $f->checkbox(
                        $this->plugin->txt('include_ip_ranges'),
                        $this->plugin->txt('include_ip_ranges_info')
                    )->withValue($this->config->include_ip_ranges),

                    'include_test_log' => $f->checkbox(
                        $this->plugin->txt('include_test_log'),
                        $this->plugin->txt('include_test_log_info')
                    )->withValue($this->plugin->isTestLogActive() && $this->config->include_test_log)
                        ->withDisabled(!$this->plugin->isTestLogActive()),

                    'include_examination_protocol' => $f->checkbox(
                        $this->plugin->txt('include_examination_protocol'),
                        $this->plugin->txt('include_examination_protocol_info')
                    )->withValue($this->plugin->isExaminationProtocolPluginActive() && $this->config->include_examination_protocol)
                        ->withDisabled(!$this->plugin->isExaminationProtocolPluginActive())
                ],
                $this->plugin->txt('privacy_settings')
            ),

            'permissions' => $f->section(
                [
                    'require_role' => $f->switchableGroup(
                        [
                            '0' => $f->group([])->withLabel($this->plugin->txt('permissions_require_role_no')),
                            '1' => $f->group([
                                'role_select' => $f->multiSelect(
                                    $this->plugin->txt('permissions_require_role_select'),
                                    $this->globalRoleOptions()
                                )->withValue($this->globalRoleValues())
                            ])->withLabel($this->plugin->txt('permissions_require_role_yes'))
                        ],
                        $this->plugin->txt('permissions_require_role')
                    )->withValue($this->config->require_global_role ? '1' : '0'),

                    'user_allow' => $f->radio($this->plugin->txt('allow'))
                        ->withOption(ilTestArchiveCreatorConfig::ALLOW_ANY, $this->plugin->txt('allow_any'), $this->plugin->txt('allow_any_info'))
                        ->withOption(ilTestArchiveCreatorConfig::ALLOW_PLANNED, $this->plugin->txt('allow_planned'), $this->plugin->txt('allow_planned_info'))
                        ->withOption(ilTestArchiveCreatorConfig::ALLOW_NONE, $this->plugin->txt('allow_none'), $this->plugin->txt('allow_none_info'))
                        ->withValue(empty($this->config->user_allow) ? ilTestArchiveCreatorConfig::ALLOW_NONE : $this->config->user_allow)
                ],
                $this->plugin->txt('permissions')
            )
        ];

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, 'saveConfiguration'),
            $inputs
        );
    }

    private function globalRoleOptions(): array
    {
        $options = [];
        foreach ($this->rbac->review()->getGlobalRoles() as $role_id) {
            $options[(string) $role_id] = ilObject::_lookupTitle($role_id);
        }

        return $options;
    }

    private function globalRoleValues(): array
    {
        $values = [];
        $options = $this->globalRoleOptions();
        foreach ($this->config->global_role_ids as $role_id) {
            if (isset($options[(string) $role_id])) {
                $values[] = (string) $role_id;
            }
        }
        return $values;
    }
}
