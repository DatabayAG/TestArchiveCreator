<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Util\Archive\Archives;
use ILIAS\Filesystem\Util\Archive\ZipDirectoryHandling;
use ILIAS\ResourceStorage\Services as ResourceStorage;
use ILIAS\TestQuestionPool\Questions\PublicInterface as QuestionService;
use ILIAS\Test\Scoring\Settings\Settings as ScoringSettings;
use ILIAS\Test\TestDIC;
use ILIAS\Test\ExportImport\Factory as ExportImportFactory;
use ILIAS\Test\ExportImport\Types as ExportImportTypes;
use ILIAS\Test\Logging\TestLoggingRepository;
use ILIAS\Test\Logging\TestLogger;
use ILIAS\Test\Logging\TestLogViewer;
use ILIAS\Test\Utilities\TitleColumnsBuilder;
use ILIAS\Test\ExportImport\DBRepository as TestExportImportRepository;
use ILIAS\Test\Participants\ParticipantRepository;
use ILIAS\Test\Participants\Participant as Participant;

/**
 * Creation of test archives
 */
class ilTestArchiveCreator
{
    // used by other classes
    public ilTestArchiveCreatorPlugin $plugin;
    public ilTestArchiveCreatorConfig $config;
    public ilTestArchiveCreatorSettings $settings;
    public ilTestArchiveCreatorFileSystems $filesystems;

    private ilObjUser $user;
    private ilLanguage $lng;
    private Filesystem $storage;
    private ResourceStorage $resource_storage;
    private QuestionService $question_info;
    private ilSetting $ilias_settings;
    private ilIniFile $client_ini;
    private ilVersionControlInformation $git_info;
    private Archives $archives;
    private ExportImportFactory $test_export_factory;
    private TestLoggingRepository $test_logging_repository;
    private TestLogger $test_logger;
    private TestLogViewer $test_log_viewer;
    private TitleColumnsBuilder $title_columns_builder;
    private TestExportImportRepository $test_export_import_repository;
    private ParticipantRepository $participant_repository;

    private ilTestArchiveCreatorAssets $assetsProcessor;
    private ilTestArchiveCreatorHTML $htmlCreator;
    private ?ilTestArchiveCreatorPDF $pdfCreator = null;

    private ilTestArchiveCreatorList $questions;
    private ilTestArchiveCreatorList $participants;
    private ilTestArchiveCreatorList $assets;
    private ilTestArchiveCreatorList $testlog;

    private int $obj_id;
    private ilObjTest $testObj;

    /** @var string relative path of the working directory in the storage */
    private string $workdir;

    /** @var string[] error messages collected during generation */
    private array $errors = [];

    /** @var  bool[] $usedQuestionIds question_id  => true  */
    private array $usedQuestionIds = [];

    /**
     * Constructor
     */
    public function __construct(ilTestArchiveCreatorPlugin $plugin, int $obj_id)
    {
        global $DIC;

        $this->plugin = $plugin;
        $this->config = $plugin->getConfig();
        $this->settings = $plugin->getSettings($obj_id);
        $this->filesystems = new ilTestArchiveCreatorFileSystems();

        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        $this->storage = $DIC->filesystem()->storage();
        $this->resource_storage = $DIC->resourceStorage();
        $this->question_info = $DIC->testQuestion();
        $this->ilias_settings = $DIC->settings();
        $this->client_ini = $DIC->clientIni();
        $this->git_info = new ilGitInformation();
        $this->archives = new Archives();

        $test_dic = TestDIC::dic();
        $this->test_export_factory = $test_dic['exportimport.factory'];
        $this->test_logging_repository = $test_dic['logging.repository'];
        $this->test_logger = $test_dic['logging.logger'];
        $this->test_log_viewer = $test_dic['logging.viewer'];
        $this->test_export_import_repository = $test_dic['exportimport.repository'];
        $this->participant_repository = $test_dic['participant.repository'];
        $this->title_columns_builder = $test_dic['title_columns_builder'];

        $this->obj_id = $obj_id;
        $this->testObj = new ilTestArchiveCreatorTest($obj_id, false, 0);
        $this->workdir = $this->plugin->getWorkdir($this->testObj->getId());

        $this->questions = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorQuestion($this));
        $this->questions->setTitle($this->plugin->txt('questions'));

        $this->participants = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorParticipant($this));
        $this->participants->setTitle($this->plugin->txt('participants'));

        $this->assets = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorAsset($this));
        $this->assets->setTitle($this->plugin->txt('assets'));

        $this->testlog = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorLogEntry($this));
        $this->testlog->setTitle($this->plugin->txt('test_log'));

        $this->htmlCreator = new ilTestArchiveCreatorHTML($this->plugin, $this->settings);
        $this->assetsProcessor = new ilTestArchiveCreatorAssets($this->assets, $this->workdir, $this->plugin->getAssetsUrl($this->testObj->getId()));

        switch ($this->config->pdf_engine) {
            case ilTestArchiveCreatorConfig::ENGINE_LOCAL:
                $this->pdfCreator = new ilTestArchiveCreatorLocal($this->plugin, $this->settings, $this->workdir);
                break;

            case ilTestArchiveCreatorConfig::ENGINE_SERVER:
                $this->pdfCreator = new ilTestArchiveCreatorServer($this->plugin, $this->settings, $this->workdir);
                break;
        }
    }


    /**
     * Create the archive
     * @return bool     created without errors
     */
    public function createArchive(): bool
    {
        $this->settings->status = ilTestArchiveCreatorPlugin::STATUS_RUNNING;
        $this->settings->save();

        $relativeDates = ilDatePresentation::useRelativeDates();
        ilDatePresentation::setUseRelativeDates(false);

        // cleanup an old generation
        if ($this->storage->hasDir($this->workdir)) {
            $this->storage->deleteDir($this->workdir);
        }

        $this->handleServerData();
        $this->handleSettings();
        $this->handleIntroduction();

        if ($this->config->with_results) {
            $this->handleResults();
        }

        if ($this->config->include_test_log && $this->plugin->isTestLogActive()) {
            $this->handleTestLog();
        }

        if ($this->config->include_examination_protocol && $this->plugin->isExaminationProtocolPluginActive()) {
            $this->handleExaminationProtocol();
        }

        // handle before questions to prefill the used question ids
        $this->handleParticipants();

        if ($this->settings->include_questions) {
            $this->handleQuestions();
        }

        // generate before list files to get the pdf hashes
        if (isset($this->pdfCreator)) {
            $this->pdfCreator->generateJobs();
            if (!empty($this->pdfCreator->getFailedFiles())) {
                $this->errors[] = "ERROR creating PDF files, see ILIAS log for details.";
            }
            $this->pdfCreator->clearJobs();
        }

        $this->handleListFiles();
        $this->handleMainIndex();

        // assets may have been copied for pdf generation only, don't put into zip
        if ($this->storage->hasDir($this->workdir . '/assets') && !$this->config->embed_assets) {
            $this->storage->deleteDir($this->workdir . '/assets');
        }

        $success = $this->createZipFile();

        if ($this->storage->hasDir($this->workdir) && !$this->config->keep_creation_directory) {
            $this->storage->deleteDir($this->workdir);
        }

        ilDatePresentation::setUseRelativeDates($relativeDates);

        $this->settings->status = ilTestArchiveCreatorPlugin::STATUS_FINISHED;
        $this->settings->save();

        return $success;
    }

    /**
     * Add a main index to the archive
     */
    protected function handleMainIndex(): void
    {
        $tpl = $this->plugin->getTemplate('tpl.main_index.html');
        $tpl->setVariable('TXT_TEST_ARCHIVE', $this->plugin->txt('test_archive'));
        $tpl->setVariable('TXT_SERVER_HTML', $this->lng->txt('server_data') . ' (HTML)');
        $tpl->setVariable('TXT_SETTINGS_HTML', $this->plugin->txt('settings_html'));
        $tpl->setVariable('TXT_INTRODUCTION_HTML', $this->plugin->txt('introduction_html'));

        if ($this->storage->has($this->workdir . '/testlog.html')) {
            $tpl->setVariable('TXT_TEST_LOG_HTML', $this->plugin->txt('test_log_html'));
            $tpl->setVariable('TXT_TEST_LOG_CSV', $this->plugin->txt('test_log_csv'));
            $tpl->setVariable('TXT_TEST_LOG_XLSX', $this->plugin->txt('test_log_xlsx'));
        }

        if ($this->storage->has($this->workdir . '/examination_protocol.html')) {
            $tpl->setVariable('TXT_EXAMINATION_PROTOCOL_HTML', $this->plugin->txt('examination_protocol_html'));
        }

        if ($this->storage->has($this->workdir . '/results.xlsx')) {
            $tpl->setVariable('TXT_TEST_RESULTS_XLSX', $this->plugin->txt('test_results_xlsx'));
        }

        if ($this->settings->include_questions) {
            $tpl->setVariable('TXT_QUESTIONS_HTML', $this->plugin->txt('questions_html'));
            $tpl->setVariable('TXT_QUESTIONS_CSV', $this->plugin->txt('questions_csv'));
        }
        if ($this->settings->include_answers) {
            $tpl->setVariable('TXT_PARTICIPANTS_HTML', $this->plugin->txt('participants_html'));
            $tpl->setVariable('TXT_PARTICIPANTS_CSV', $this->plugin->txt('participants_csv'));
        }
        $tpl->setVariable('TXT_GENERATED', $this->plugin->txt('label_generated'));
        $tpl->setVariable('VAL_GENERATED', ilDatePresentation::formatDate(new ilDateTime(time(), IL_CAL_UNIX)));

        $this->createIndex('index.html', $tpl->get());
    }

    /**
     * Add the server data
     */
    protected function handleServerData(): void
    {
        $this->lng->loadLanguageModule('administration');

        $info = array();
        $info[$this->lng->txt("inst_name")] = $this->client_ini->readVariable("client", "name");
        $info[$this->lng->txt("description")] = $this->client_ini->readVariable("client", "description");
        $info[$this->lng->txt("client_id")] = CLIENT_ID;
        $info[$this->lng->txt("inst_id")] = $this->ilias_settings->get("inst_id");
        $info[$this->lng->txt("ilias_version")] = ILIAS_VERSION;
        $info[$this->plugin->txt("git_info")] = $this->git_info->getInformationAsHtml();

        // fill the template
        $tpl = $this->plugin->getTemplate('tpl.settings.html');
        foreach ($info as $label => $content) {
            $tpl->setCurrentBlock('data_row');
            $tpl->setVariable('LABEL', $label);
            $tpl->setVariable('CONTENT', $content);
            $tpl->parseCurrentBlock();
        }
        $tpl->setVariable('TXT_SETTINGS', $this->lng->txt('server_data'));

        $this->createIndex('server.html', $tpl->get());
    }

    /**
     * Add the test settings to the archive
     */
    protected function handleSettings(): void
    {
        // get the basic properties
        $info = array();
        $info[$this->lng->txt('title')] = $this->testObj->getTitle();
        $info[$this->lng->txt("tst_question_set_type")] = $this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_FIXED ?
            $this->lng->txt("tst_question_set_type_fixed") : $this->lng->txt("tst_question_set_type_random");
        $info[$this->lng->txt("tst_nr_of_tries")] = $this->testObj->getNrOfTries() > 0 ?
            $this->testObj->getNrOfTries() : $this->lng->txt('unlimited');
        $info[$this->lng->txt("tst_processing_time_duration")] = $this->testObj->getEnableProcessingTime() ?
            (int) ($this->testObj->getProcessingTimeInSeconds() / 60) . ' ' . $this->lng->txt('minutes') : $this->lng->txt('unlimited');
        $info[$this->lng->txt("tst_shuffle_questions")] = $this->testObj->getShuffleQuestions() ?
            $this->lng->txt("tst_shuffle_questions_description") : $this->lng->txt('no');
        $info[$this->lng->txt("tst_text_count_system")] = $this->lng->txt(($this->testObj->getCountSystem() == ScoringSettings::COUNT_PARTIAL_SOLUTIONS) ? "tst_count_partial_solutions" : "tst_count_correct_solutions");
        $info[$this->lng->txt("tst_pass_scoring")] = $this->lng->txt(($this->testObj->getPassScoring() == ilObjTest::SCORE_BEST_PASS) ? "tst_pass_best_pass" : "tst_pass_last_pass");

        // get the mark scheme
        $scheme = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorMark($this));
        $scheme->setTitle($this->lng->txt('mark_schema'));
        $marks = $this->testObj->getMarkSchema()->getMarkSteps();
        foreach ($marks as $value) {
            $mark = new ilTestArchiveCreatorMark($this);
            $mark->short_form = (string) $value->getShortName();
            $mark->official_form = (string) $value->getOfficialName();
            $mark->minimum_level = (string) $value->getMinimumLevel();
            $mark->passed = (string) ($value->getPassed() ? $this->lng->txt('yes') : $this->lng->txt('no'));
            $scheme->add($mark);
        }

        // fill the template
        $tpl = $this->plugin->getTemplate('tpl.settings.html');
        foreach ($info as $label => $content) {
            $tpl->setCurrentBlock('data_row');
            $tpl->setVariable('LABEL', $label);
            $tpl->setVariable('CONTENT', $content);
            $tpl->parseCurrentBlock();
        }
        $tpl->setVariable('TXT_SETTINGS', $this->plugin->txt('test_settings'));
        $tpl->setVariable('MARK_SCHEME', $scheme->getHTML());

        $this->createIndex('settings.html', $tpl->get());
    }

    public function handleIntroduction()
    {
        $this->htmlCreator->initMainTemplate();

        $file = 'introduction';
        $title = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';
        $description = $this->testObj->getDescription();

        $page_id = $this->testObj->getMainSettings()->getIntroductionSettings()->getIntroductionPageId();
        if ($page_id !== null) {
            $page = new ilTestPageGUI('tst', $page_id);
            $page->setTemplate($this->htmlCreator->getMainTemplate());
            $content = $page->showPage();


            $head_left = $title;
            $head_right = '';

            $this->createContent($file, $title, $description, $content, $head_left, $head_right);
        }
    }

    /**
     * Write the result export files
     */
    protected function handleResults(): void
    {
        // we need to create new test objects
        // otherwise the first generation causes empty results in the second
        // ANONYMOUS_USED_ID needed to prevent error with deleted accounts
        $test_obj = new ilTestArchiveCreatorTest($this->obj_id, false, ANONYMOUS_USER_ID);

        $exporter = $this->test_export_factory->getExporter($this->testObj, match($this->settings->pass_selection) {
            ilTestArchiveCreatorPlugin::PASS_SCORED => ExportImportTypes::SCORED_ATTEMPT,
            default => ExportImportTypes::ALL_ATTEMPTS,
        });

        $absolute_path = $exporter->write();
        $source_fs = $this->filesystems->deriveFilesystemFrom($absolute_path);
        $relative_path = $this->filesystems->createRelativePath($absolute_path);

        $this->storage->writeStream($this->workdir . '/results.xlsx', $source_fs->readStream($relative_path));
    }

    /**
     * Add the test log to the archive
     */
    public function handleTestLog(): void
    {
        $ref_ids = ilObject::_getAllReferences($this->testObj->getId());

        foreach ($this->test_logging_repository->getLogs($this->test_logger->getInteractionTypes(), $ref_ids) as $log) {

            $row = $log->getLogEntryAsExportRow(
                $this->lng,
                $this->title_columns_builder,
                $this->test_logger->getAdditionalInformationGenerator(),
                [
                    'timezone' => new \DateTimeZone($this->user->getTimeZone()),
                    'date_format' => $this->test_log_viewer->buildUserDateTimeFormat()->toString()
                ]
            );

            $entry = new ilTestArchiveCreatorLogEntry($this);
            $entry->date_time = (string) ($row[0] ?? '');
            $entry->test = (string) ($row[1] ?? '');
            $entry->author = (string) ($row[2] ?? '');
            $entry->tst_participant = (string) ($row[3] ?? '');
            $entry->client_ip = (string) ($row[4] ?? '');
            $entry->question = (string) ($row[5] ?? '');
            $entry->log_entry_type = (string) ($row[6] ?? '');
            $entry->interaction_type = (string) ($row[7] ?? '');
            $entry->additional_info = (string) ($row[8] ?? '');

            $this->testlog->add($entry);
        }

        $workbook = $this->test_log_viewer->buildExcelWorkbookForLogs(
            $this->test_logging_repository->getLogs($this->test_logger->getInteractionTypes(), $ref_ids)
        );
        $workbook->writeToFile(CLIENT_DATA_DIR . '/' . $this->workdir . '/testlog.xlsx');

        $this->createFile('testlog.csv', $this->testlog->getCSV());
        $this->createIndex('testlog.html', $this->testlog->getHTML());
    }


    /**
     * Add the examination protocol to the archive
     */
    public function handleExaminationProtocol(): void
    {
        global $DIC;

        try {
            /** @var ilExaminationProtocolPlugin $plugin */
            $plugin = $this->plugin->getExaminationProtocolPlugin();
            $identifier = $plugin->getProtocolExportByTestID($this->testObj->getTestId());
            $irss = $DIC->resourceStorage();
            $content = $irss->consume()->stream($identifier)->getStream()->getContents();
            $this->createFile('examination_protocol.html', $content);
        } catch (Exception $e) {
            // do nothing
        }
    }

    /**
     * Add the test questions to the archive
     */
    protected function handleQuestions(): void
    {
        // Title for header in PDFs
        $title = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';
        $description = $this->testObj->getDescription();

        $question_ids = array();
        switch ($this->testObj->getQuestionSetType()) {
            case ilObjTest::QUESTION_SET_TYPE_FIXED:
                $question_ids = $this->testObj->getQuestions();
                break;

            case ilObjTest::QUESTION_SET_TYPE_RANDOM:
                if ($this->settings->random_questions == ilTestArchiveCreatorPlugin::RANDOM_ALL) {
                    $question_data = $this->testObj->getPotentialRandomTestQuestions();
                    foreach ($question_data as $row) {
                        $question_ids[] = $row['question_id'];
                    }
                } else {
                    $question_ids = array_keys($this->getUsedQuestionIds());
                }
        }

        foreach ($question_ids as $question_id) {
            $this->htmlCreator->initMainTemplate();
            $question_gui = $this->testObj->createQuestionGUI("", $question_id);
            $content = $question_gui->getPreview(true);
            $content = $this->addILIASPage((int) $question_id, $content);

            $properties = $this->question_info->getGeneralQuestionProperties($question_id);


            // add the list entry
            $element = new ilTestArchiveCreatorQuestion($this);
            $element->question_id = (int) $question_id;
            $element->exam_question_id = (string) $this->plugin->buildExamQuestionId($this->testObj, $question_id);
            $element->title = (string) $properties->getTitle();
            $element->type = (string) $properties->getTypeName($this->lng);
            $element->max_points = (float) $properties->getAvailablePoints();
            $this->questions->add($element);

            // create presentation files
            $tpl = $this->plugin->getTemplate('tpl.question.html');
            $tpl->setVariable('QUESTION_ID', $question_id);
            $tpl->setVariable('TITLE', $properties->getTitle());
            $tpl->setVariable('CONTENT', $content);

            $question_dir = 'questions/' . $element->getFolderName();
            $file = $question_dir . '/' . $element->getFilePrefix() . '_presentation';
            $element->presentation = $file;
            $this->createContent($file, $title, $description, $tpl->get(), $title, $properties->getTitle());

            if ($this->settings->questions_with_best_solution) {
                // re-initialize the template and gui for a new generation
                $this->htmlCreator->initMainTemplate();
                $question_gui = $this->testObj->createQuestionGUI("", $question_id);
                $content = $question_gui->getSolutionOutput(
                    0,
                    null,
                    true,
                    true,
                    true,
                    false,
                    true,
                    false
                );
                $content = $this->addILIASPage((int) $question_id, $content);

                // create best solution files
                $tpl = $this->plugin->getTemplate('tpl.question.html');
                $tpl->setVariable('QUESTION_ID', $question_id);
                $tpl->setVariable('TITLE', $properties->getTitle());
                $tpl->setVariable('CONTENT', $content);

                $file = $question_dir . '/' . $element->getFilePrefix() . '_best_solution';
                $element->best_solution = $file;
                $this->createContent($file, $title, $description, $tpl->get(), $title, $properties->getTitle());
            }

            unset($question_gui);
        }
    }

    /**
     * Add the participants to the archive
     */
    protected function handleParticipants(): void
    {
        $all_participants = $this->participant_repository->getParticipants($this->testObj->getTestId());
        $active_participants = $this->testObj->getUnfilteredEvaluationData()->getParticipants();

        /** @var Participant $participant */
        foreach ($all_participants as $participant) {
            $user = new ilObjUser($participant->getUserId() ?? 0);
            if ($user->getId() === 0) {
                $user->setLastname($this->plugin->txt('deleted_account'));
            }

            $element = new ilTestArchiveCreatorParticipant($this);
            $element->active_id = $participant->getActiveId() ?? 0;
            $element->firstname = $user->getFirstname();
            $element->lastname = $user->getLastname();
            $element->fullname = $user->getFullname();
            $element->login = $user->getLogin();
            $element->matriculation = $user->getMatriculation();
            $element->email = $user->getEmail();
            $element->ip_range_from = $participant->getClientIpFrom() ?? '';
            $element->ip_range_to = $participant->getClientIpTo() ?? '';

            $userdata = $active_participants[$element->active_id] ?? null;
            if ($userdata === null) {
                $element->exam_id = $this->plugin->buildExamId($this->testObj);
                $this->participants->add($element);
            } else {

                switch ($this->settings->pass_selection) {
                    case ilTestArchiveCreatorPlugin::PASS_ALL:
                        $passes = $userdata->getPasses();
                        break;
                    case ilTestArchiveCreatorPlugin::PASS_SCORED:
                    default:
                        $passes = array($userdata->getScoredPassObject());
                        break;
                }

                foreach ($passes as $passdata) {
                    if ($passdata instanceof ilTestEvaluationPassData) {
                        $pass = $passdata->getPass();
                        $exam_id = $this->plugin->buildExamId($this->testObj, $element->active_id, $pass);

                        $element = clone($element);
                        $element->exam_id = $exam_id;
                        $element->pass_number = $passdata->getPass() + 1;
                        $element->pass_scored = ($userdata->getScoredPass() == $passdata->getPass());
                        $element->pass_working_time = $passdata->getWorkingTime();
                        $element->pass_finish_date = (int) $this->testObj->lookupLastTestPassAccess($element->active_id, $passdata->getPass());
                        $element->pass_reached_points = $passdata->getReachedPoints();

                        $this->participants->add($element);

                        if ($this->settings->include_answers) {
                            $this->createAnswersFile($element, $userdata, $passdata);
                        }
                    }
                }
            }
        }
    }

    public function createAnswersFile(
        ilTestArchiveCreatorParticipant $element,
        ilTestEvaluationUserData $userdata,
        ilTestEvaluationPassData $passdata,
    ) {
        $this->htmlCreator->initMainTemplate();
        $tpl = $this->plugin->getTemplate('tpl.participant.html');

        // test data of the user
        $info = array();
        $info[$this->lng->txt('firstname')] = $element->firstname;
        $info[$this->lng->txt('lastname')] = $element->lastname;
        if ($this->config->with_login) {
            $info[$this->lng->txt('login')] = $element->login;
        }
        if ($this->config->with_matriculation) {
            $info[$this->lng->txt('matriculation')] = $element->matriculation;
        }
        $info[$this->lng->txt('email')] = $element->email;

        $info[$this->plugin->txt('first_visit')] = ilDatePresentation::formatDate(
            new ilDateTime($userdata->getFirstVisit()->getTimestamp(), IL_CAL_UNIX)
        )
            . ' (' . $userdata->getFirstVisit()->getTimestamp() . ')';

        $info[$this->plugin->txt('last_visit')] = ilDatePresentation::formatDate(
            new ilDateTime($userdata->getLastVisit()->getTimestamp(), IL_CAL_UNIX)
        )
            . ' (' . $userdata->getLastVisit()->getTimestamp() . ')';

        $info[$this->plugin->txt('number_passes')] = $userdata->getPassCount();
        $info[$this->plugin->txt('scored_pass')] = $userdata->getScoredPass() + 1;
        $info[$this->plugin->txt('reached_points')] = $userdata->getReached();
        $info[$this->plugin->txt('mark_official')] = $userdata->getMark()->getOfficialName();
        $info[$this->plugin->txt('mark_short')] = $userdata->getMark()->getShortName();
        $info[$this->plugin->txt('final_result')] = $userdata->getMark()->getPassed() ?
            $this->plugin->txt('passed') : $this->plugin->txt('not_passed');

        foreach ($info as $label => $content) {
            $tpl->setCurrentBlock('data_row');
            $tpl->setVariable('LABEL', $label);
            $tpl->setVariable('CONTENT', $content);
            $tpl->parseCurrentBlock();
        }

        // this works for all question set types
        $questions = $this->getPassQuestionData($element->active_id, $passdata->getPass());

        foreach ($questions as $row) {
            // needed for question selection in random tests
            $this->usedQuestionIds[$row['qid']] = true;

            // pass overview of questions
            $tpl->setCurrentBlock('question_row');
            $tpl->setVariable('SEQUENCE', $row['nr']);
            $tpl->setVariable('QUESTION_ID', $row['qid']);
            $tpl->setVariable('QUESTION_TITLE', $row['title']);
            $tpl->setVariable('ANSWERED', $row['workedthrough'] ? $this->lng->txt('yes') : $this->lng->txt('no'));
            $tpl->setVariable('MAX_POINTS', $row['max']);
            $tpl->setVariable('REACHED_POINTS', $row['reached']);
            $tpl->setVariable('REACHED_PERCENT', $row['percent']);
            $tpl->setVariable('MANUAL', $row['manual'] ? $this->lng->txt('yes') : $this->lng->txt('no'));
            $tpl->parseCurrentBlock();


            // answer and solution output
            $question_gui = $this->testObj->createQuestionGUI($row['type'], $row['qid']);
            $html_answer = $question_gui->getSolutionOutput(
                $element->active_id,
                $passdata->getPass(),
                true,   // $graphicalOutput
                false,  // $result_output
                true,   // $show_question_only
                false,  // $show_feedback
                false,  // $show_correct_solution
                $question_gui instanceof assFileUploadGUI,   // $show_manual_scoring
                true    // $show_question_text
            );
            $html_answer = $this->addILIASPage((int) $row['qid'], $html_answer);

            if ($this->settings->answers_with_best_solution) {
                $html_solution = $question_gui->getSolutionOutput($element->active_id, $passdata->getPass(), false, false, true, false, true);
                $html_solution = $this->addILIASPage((int) $row['qid'], $html_solution);
            }

            //manual feedback
            if (!empty($row['manualFeedback'])) {
                $tpl->setCurrentBlock('manual_feedback');
                $tpl->setVariable('TXT_MANUAL_FEEDBACK', $this->plugin->txt('manual_feedback'));
                $tpl->setVariable('HTML_MANUAL_FEEDBACK', $feedback = ilRTE::_replaceMediaObjectImageSrc($row['manualFeedback'], 1));
                $tpl->parseCurrentBlock();
            }
            $tpl->setCurrentBlock('question_detail');
            $tpl->setVariable('SEQUENCE', $row['nr']);
            $tpl->setVariable('QUESTION_TITLE', $row['title']);
            $tpl->setVariable('QUESTION_ID', $row['qid']);
            $tpl->setVariable('TXT_REACHED_POINTS', $this->lng->txt('tst_reached_points'));
            $tpl->setVariable('REACHED_POINTS_OF', sprintf($this->plugin->txt('reached_points_of'), $row['reached'], $row['max']));
            $tpl->setVariable('TXT_GIVEN_ANSWER', $this->plugin->txt('given_answer'));
            $tpl->setVariable('HTML_ANSWER', $html_answer);

            if ($this->settings->answers_with_best_solution) {
                $tpl->setVariable('TXT_BEST_SOLUTION', $this->plugin->txt('question_best_solution'));
                $tpl->setVariable('HTML_SOLUTION', $html_solution);
            }

            $tpl->parseCurrentBlock();

            unset($question_gui);
        }

        $tpl->setVariable('TXT_SEQUENCE', $this->lng->txt('tst_question_no'));
        $tpl->setVariable('TXT_QUESTION_ID', $this->lng->txt('question_id'));
        $tpl->setVariable('TXT_QUESTION_TITLE', $this->lng->txt('tst_question_title'));
        $tpl->setVariable('TXT_ANSWERED', $this->plugin->txt('answered'));
        $tpl->setVariable('TXT_MAX_POINTS', $this->lng->txt('tst_maximum_points'));
        $tpl->setVariable('TXT_REACHED_POINTS', $this->lng->txt('tst_reached_points'));
        $tpl->setVariable('TXT_REACHED_PERCENT', $this->lng->txt('tst_percent_solved'));
        $tpl->setVariable('TXT_MANUAL', $this->plugin->txt('manual'));

        $tpl->setVariable('TXT_PARTICIPANT', $this->plugin->txt('participant'));
        $tpl->setVariable('TXT_PASS_OVERVIEW', sprintf($this->plugin->txt('pass_overview'), $passdata->getPass() + 1));
        $tpl->setVariable('TXT_PASS_FINISH_DATE', $this->plugin->txt('finish_date'));
        $tpl->setVariable('PASS_FINISH_DATE', ilDatePresentation::formatDate(
            new ilDateTime($element->pass_finish_date, IL_CAL_UNIX)
        ));

        $title = $this->testObj->getTitle() . ' [' . $element->exam_id . ']';
        $description = $this->testObj->getDescription();
        $head_left = $title;
        $head_right = $element->fullname;

        $participant_dir = 'participants/' . $element->getFolderName();
        $file = $participant_dir . '/' . $element->getFilePrefix() . '_answers';
        $element->answers_file = $file;
        $this->createContent($file, $title, $description, $tpl->get(), $head_left, $head_right);
    }

    /**
     * Write the list files for questions and participants
     */
    protected function handleListFiles(): void
    {
        /** @var ilTestArchiveCreatorParticipant $participant */
        foreach ($this->participants->elements as $participant) {
            $file = null;
            if ($this->storage->has($this->workdir . '/' . $participant->answers_file . '.pdf')) {
                $file = $participant->answers_file . '.pdf';
                $participant->setHasPdf(true);
            } elseif ($this->storage->has($this->workdir . '/' . $participant->answers_file . '.html')) {
                $file = $participant->answers_file . '.html';
                $participant->setHasPdf(false);
            }
            if ($file !== null) {
                $content = $this->storage->read($this->workdir . '/' . $file);
                $participant->answers_hash = sha1($content);
            }
        }

        /** @var ilTestArchiveCreatorQuestion $question */
        foreach ($this->questions->elements as $question) {
            $question->setHasPdf($this->storage->has($this->workdir . '/' . $question->presentation . '.pdf'));
        }

        // questions
        if ($this->settings->include_questions) {
            $this->createFile('questions.csv', $this->questions->getCSV());
            $this->createIndex('questions.html', $this->questions->getHTML());
        }

        // participants
        $this->createFile('participants.csv', $this->participants->getCSV());
        $this->createIndex('participants.html', $this->participants->getHTML());

        // assets
        if ($this->config->embed_assets) {
            $this->createFile('assets.csv', $this->assets->getCSV());
            $this->createIndex('assets.html', $this->assets->getHTML());
        }
    }


    /**
     * Get the used question ids
     * The array is also filled in handleParticipants()
     * It will be filled here if participants are not included
     *
     * @return bool[]    id => true
     */
    protected function getUsedQuestionIds(): array
    {
        if (!is_array($this->usedQuestionIds)) {
            $this->usedQuestionIds = [];
            $participants = $this->testObj->getUnfilteredEvaluationData()->getParticipants();

            /** @var  ilTestEvaluationUserData $userdata */
            foreach ($participants as $active_id => $userdata) {
                if (is_object($userdata) && is_array($userdata->getPasses())) {
                    switch ($this->settings->pass_selection) {
                        case ilTestArchiveCreatorPlugin::PASS_ALL:
                            $passes = $userdata->getPasses();
                            break;
                        case ilTestArchiveCreatorPlugin::PASS_SCORED:
                        default:
                            $passes = array($userdata->getScoredPassObject());
                    }

                    foreach ($passes as $passdata) {
                        if ($passdata instanceof ilTestEvaluationPassData) {
                            $pass = $passdata->getPass();
                            $questions = $this->getPassQuestionData($active_id, $pass);
                            foreach ($questions as $row) {
                                $this->usedQuestionIds[$row['qid']] = true;
                            }
                        }
                    }
                }
            }
        }
        return $this->usedQuestionIds;
    }

    /**
     * Add the ILIAS page around a question
     * Currently the page is rendered in PRESENTATION mode and the controller is tweaked by the plugin
     * If that causes problems, switching to OFFLINE here would be an alternative
     *
     * @see ilTestArchiveCreatorPlugin::initCtrl
     * @see assQuestionGUI::getILIASPage
     */
    protected function addILIASPage(int $question_id, string $html = ""): string
    {
        $page_gui = new ilAssQuestionPageGUI($question_id);
        $page_gui->setQuestionHTML(
            [$question_id => $html]
        );
        $presentation = $page_gui->presentation(ilPageObjectGUI::PRESENTATION);

        // like in assQuestionGUI::getILIASPage()
        $presentation = preg_replace("/src=\"\\.\\//ims", "src=\"" . ILIAS_HTTP_PATH . "/", $presentation);

        // revert local media paths that are generate for the offline mode
        $presentation = preg_replace('/src=\"mobs\//ims', "src=\"" . ILIAS_HTTP_PATH . "/data/" . CLIENT_ID . '/mobs/', $presentation);

        return $presentation;
    }


    /**
     * Get the pass question data for a dynamic test
     * @param	int		$active_id
     * @param	int		$pass
     * @return array
     */
    protected function getPassQuestionData($active_id, $pass): array
    {
        global $DIC;
        $ilDB = $DIC->database();

        $questions = array();
        $result_array = $this->testObj->getTestResult($active_id, $pass, false);
        foreach ($result_array as $key => $data) {
            if ($key === 'test' || $key === 'pass') {
                continue;
            }

            $question = array();
            $question_id = $data['qid'];
            $question['qid'] = $data['qid'];
            $question['title'] = $data['title'];
            $question["type"] = $data['type'];
            $question['nr'] = $data['nr'];
            $question['max'] = $data['max'];
            $question['reached'] = $data['reached'];
            $question['percent'] = $data['percent'];
            $question['workedthrough'] = $data['workedthrough'];
            $question['manual'] = 0;
            $question['manualFeedback'] = '';
            $questions[$question_id] = $question;
        }

        // get man scoring and feeback (not provided by getTestResult)
        $query = "SELECT question_fi, manual FROM tst_test_result WHERE active_fi = %s AND pass = %s AND manual = 1";
        $result = $ilDB->queryF($query, array('integer', 'integer'), array($active_id, $pass));
        while ($row = $ilDB->fetchAssoc($result)) {
            if (isset($questions[$row['question_fi']])) {
                $questions[$row['question_fi']]['manual'] = 1;
            }
        }
        $query = "SELECT question_fi, feedback FROM tst_manual_fb WHERE active_fi = %s AND pass = %s";
        $result = $ilDB->queryF($query, array('integer', 'integer'), array($active_id, $pass));
        while ($row = $ilDB->fetchAssoc($result)) {
            if (isset($questions[$row['question_fi']])) {
                $questions[$row['question_fi']]['manualFeedback'] = $row['feedback'];
            }
        }

        // Sort the pass question data by sequence
        $sorted_questions = array();
        foreach ($questions as $question) {
            $key = sprintf('%09d', (int) $question['nr']) . sprintf('%09d', (int) $question['qid']);
            $sorted_questions[$key] = $question;
        }
        ksort($sorted_questions);

        return array_values($sorted_questions);
    }

    /**
     * Create an index file (HTML only, simple template without assets)
     *
     * @param string $file    path and filename (WITH extension) relative to the working directory
     * @param string $content html content that
     */
    protected function createIndex(string $file, string $content): void
    {
        $html = $this->htmlCreator->buildIndex(
            $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']',
            $this->testObj->getDescription(),
            $content
        );

        $this->createFile($file, $html);
    }

    /**
     * Create content files (HTML and/or PDF) with asset handling
     * Assets will be copied to the archive if this is chosen in the settings
     * An HTML file is always created
     *      - will have the original asset paths if assets are not included
     *      - will have the local asset paths if assets are included
     * A PDF file is optionally created
     *      - will use paths to the asset delivery script
     *
     * @param string $file          path and filename (WITHOUT extension) relative to the working directory
     * @param string $title         title that should be written as headline in the file
     * @param string $description   description that should be written as paragraph below the headline
     * @param string $content       html content that
     * @param string $headLeft      left header of a PDF file
     * @param string $headRight     right header of a PDF file
     */
    protected function createContent(
        string $file,
        string $title,
        string $description,
        string $content,
        string $headLeft,
        string $headRight
    ): void {
        $html = $this->htmlCreator->buildContent($title, $description, $content, false);
        if ($this->config->embed_assets) {
            $this->createFile($file . '.html', $this->assetsProcessor->processForEmbedding($html, $file));
        } else {
            $this->createFile($file . '.html', $html);
        }

        if (isset($this->pdfCreator)) {
            $html = $this->htmlCreator->buildContent($title, $description, $content, true);
            $this->createFile($file . '.job.html', $this->assetsProcessor->processForPdfGeneration($html));
            $this->pdfCreator->addJob($file . '.job.html', $file . '.pdf', $headLeft, $headRight);
        }
    }


    /**
     * Write a file in the working directory
     * @param string $file  path and filename (WITH extension) relative to the workdir
     * @param string $content
     */
    protected function createFile(string $file, string $content): void
    {
        $path = $this->workdir . '/' . $file;

        try {
            // prevent FileAlreadyExistsException and ensure newest content
            if ($this->storage->has($path)) {
                $this->storage->delete($path);
            }
            $this->storage->write($path, $content);
        } catch (Exception $exception) {
            $this->errors[] = "ERROR writing $file :" . $exception->getMessage();
        }
    }

    /**
     * Create a zip file from the working directory and store it in the export directory of the test
     * @return bool success
     */
    protected function createZipFile(): bool
    {
        $export_dir = 'tst_data/archive_exports/tst_' . $this->testObj->getId();
        $title = $this->settings->file_prefix . 'test_archive_obj_' . $this->testObj->getId() . '_' . time() . '_plugin.zip';

        try {
            if (!$this->storage->hasDir($export_dir)) {
                $this->storage->createDir($export_dir);
            }

            $zip = $this->archives->zip(
                [],
                $this->archives->zipOptions()
                    ->withZipOutputPath(CLIENT_DATA_DIR . '/' . $export_dir)
                    ->withZipOutputName($title)
                    ->withDirectoryHandling(ZipDirectoryHandling::ENSURE_SINGLE_TOP_DIR)
            );

            $zip->addDirectory(CLIENT_DATA_DIR . '/' . $this->workdir);

            $id = $this->resource_storage->manage()->stream(
                $zip->get(),
                new ilTestArchiveCreatorStakeholder(),
                $title
            );

            $this->test_export_import_repository->store($this->testObj->getId(), ExportImportTypes::PLUGIN, $id);
            $this->storage->delete($export_dir . '/' . $title);

        } catch (Exception $exception) {
            $this->errors[] = "ERROR writing zip file" . $exception->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Get the errors collected during the creation
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
