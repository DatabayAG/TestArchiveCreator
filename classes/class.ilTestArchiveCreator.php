<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Creation of test archives
 */
class ilTestArchiveCreator
{
	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorConfig $config */
	public $config;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;

	/** @var ilObjTest $testObj */
	public $testObj;

	/** @var  ilDB $db */
	protected $db;

	/** @var ilLanguage $lng */
	protected $lng;

	/** @var  string absolute path of the working directory */
	protected $workdir;

	/** @var  ilTestArchiveCreatorList $questions */
	protected $questions;

	/** @var ilTestArchiveCreatorList $participants */
	protected $participants;

	/** @var ilTestArchiveCreatorList $properties */
	protected $properties;

	/** @var  ilTestArchiveCreatorPDF */
	protected $pdfCreator;

	/** @var  ilTestArchiveCreatorHTML */
	protected $htmlCreator;

	/** @var  array question_id  => true */
	/** @see getUsedQuestionIds() */
	protected $usedQuestionIds;

	/**
	 * Constructor
	 * @param ilTestArchiveCreatorPlugin $plugin
	 * @param int $obj_id test object id
	 */
	public function __construct($plugin, $obj_id)
	{
		global $DIC;

		$this->db = $DIC->database();
		$this->lng = $DIC->language();

		$this->plugin = $plugin;
		$this->config = $plugin->getConfig();
		$this->settings = $plugin->getSettings($obj_id);

		require_once ('Modules/Test/classes/class.ilObjTest.php');
		$this->testObj = new ilObjTest($obj_id, false);

		// workaround for missing include in ilObjTest::getQuestionCount()
		if ($this->testObj->isRandomTest())
		{
			require_once('Modules/Test/classes/class.ilTestRandomQuestionSetConfig.php');
		}
	}


	/**
	 * Create the archive
	 */
	public function createArchive()
	{
		$this->initCreation();

		$this->handleSettings();

		$this->handleSolution();

		if ($this->settings->include_answers)
        {
            // handle before questions to prefill the question ids
            $this->handleParticipants();
        }
        if ($this->settings->include_questions)
        {
            $this->handleQuestions();
        }
		$this->handleMainIndex();

        // generate before index files to enable hashes
		$this->pdfCreator->generateJobs();
		$this->pdfCreator->clearJobs();

		$this->writeIndexFiles();
		$this->finishCreation();

		return true;
	}

	/**
	 * Initialize the archive creation
	 * prepare working directory and lists
	 */
	protected function initCreation()
	{
		require_once('Services/Calendar/classes/class.ilDatePresentation.php');
		ilDatePresentation::setUseRelativeDates(false);

		$this->workdir = CLIENT_DATA_DIR . '/tst_data/archive_plugin/tst_'.$this->testObj->getId();
		ilUtil::delDir($this->workdir);
		ilUtil::makeDirParents($this->workdir);

		$this->plugin->includeClass('class.ilTestArchiveCreatorPDF.php');
		$this->plugin->includeClass('class.ilTestArchiveCreatorHTML.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorElement.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorList.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorQuestion.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorParticipant.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorMark.php');

		$this->htmlCreator = new ilTestArchiveCreatorHTML($this->plugin, $this->settings, $this->testObj);
		$this->pdfCreator = new ilTestArchiveCreatorPDF($this->plugin, $this->settings, $this->workdir);

		$this->questions = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorQuestion($this));
		$this->questions->setTitle($this->plugin->txt('questions'));

		$this->participants = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorParticipant($this));
		$this->participants->setTitle($this->plugin->txt('participants'));
	}


	/**
	 * Finish the archive creation
	 * Generate the PDF files
	 * Zip the directory
	 */
	protected function finishCreation()
	{
		$export_dir = CLIENT_DATA_DIR . '/tst_data/archive_exports/tst_'.$this->testObj->getId();
		ilUtil::makeDirParents($export_dir);

		$zipfile = 'test_archive_obj_'.$this->testObj->getId().'_'.time().'_plugin';
		ilUtil::zip($this->workdir, $export_dir .'/'. $zipfile, true);

		if (!$this->config->keep_creation_directory)
		{
			ilUtil::delDir($this->workdir);
		}
	}

	/**
	 * Add a main index to the archive
	 */
	protected function handleMainIndex()
	{
		$tpl = $this->plugin->getTemplate('tpl.main_index.html');
		$tpl->setVariable('TXT_TEST_ARCHIVE', $this->plugin->txt('test_archive'));
		$tpl->setVariable('TXT_SETTINGS_HTML', $this->plugin->txt('settings_html'));
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

		$source_file = 'index.html';
		$head_left = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';
		$this->htmlCreator->initIndexTemplate();
		$this->writeFile($source_file, $this->htmlCreator->build(
			$head_left, $this->testObj->getDescription(), $tpl->get()));
	}

	/**
	 * Add the test properties to the archiv
	 */
	protected function handleSettings()
	{
		// get the basic properties
		$info = array();
		$info[$this->lng->txt('title')] = $this->testObj->getTitle();
		$info[$this->lng->txt("tst_introduction")] = $this->testObj->getIntroduction();
		$info[$this->lng->txt("tst_question_set_type")] = $this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_FIXED ?
			$this->lng->txt("tst_question_set_type_fixed") : ($this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_RANDOM ?
				$this->lng->txt("tst_question_set_type_random") : ($this->testObj->getQuestionSetType() == ilObjTest::QUESTION_SET_TYPE_DYNAMIC ?
					$this->lng->txt("tst_question_set_type_dynamic") : ''));
		$info[$this->lng->txt("tst_nr_of_tries")] = $this->testObj->getNrOfTries() > 0 ?
			$this->testObj->getNrOfTries() : $this->lng->txt('unlimited');
		$info[$this->lng->txt("tst_processing_time_duration")] = $this->testObj->getEnableProcessingTime() ?
			$this->testObj->getProcessingTimeAsMinutes(). ' ' . $this->lng->txt('minutes') : $this->lng->txt('unlimited');
		$info[$this->lng->txt("tst_shuffle_questions")] = $this->testObj->getShuffleQuestions() ?
			$this->lng->txt("tst_shuffle_questions_description") : $this->lng->txt('no');
		$info[$this->lng->txt("tst_text_count_system")] = $this->lng->txt(($this->testObj->getCountSystem() == COUNT_PARTIAL_SOLUTIONS)? "tst_count_partial_solutions":"tst_count_correct_solutions");
		$info[$this->lng->txt("tst_score_mcmr_questions")]= $this->lng->txt(($this->testObj->getMCScoring() == SCORE_ZERO_POINTS_WHEN_UNANSWERED)? "tst_score_mcmr_zero_points_when_unanswered":"tst_score_mcmr_use_scoring_system");
		$info[$this->lng->txt("tst_pass_scoring")] = $this->lng->txt(($this->testObj->getPassScoring() == SCORE_BEST_PASS)? "tst_pass_best_pass":"tst_pass_last_pass");

		// get the mark scheme
		$scheme = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorMark($this));
		$scheme->setTitle($this->lng->txt('mark_schema'));
		// will make problems, if called twice in cron job, but not needed because list will be sorted
		//$this->testObj->getMarkSchema()->sort();
		$marks = $this->testObj->getMarkSchema()->getMarkSteps();
		foreach($marks as $key => $value)
		{
			$mark = new ilTestArchiveCreatorMark($this);
			$mark->short_form = $value->getShortName();
			$mark->official_form = $value->getOfficialName();
			$mark->minimum_level = $value->getMinimumLevel();
			$mark->passed = $value->getPassed() ? $this->lng->txt('yes') : $this->lng->txt('no');
			$scheme->add($mark);
		}

		// fill the template
		$tpl = $this->plugin->getTemplate('tpl.settings.html');
		foreach ($info as $label => $content)
		{
			$tpl->setCurrentBlock('data_row');
			$tpl->setVariable('LABEL', $label);
			$tpl->setVariable('CONTENT', $content);
			$tpl->parseCurrentBlock();
		}
		$tpl->setVariable('TXT_SETTINGS', $this->plugin->txt('test_settings'));
		$tpl->setVariable('MARK_SCHEME', $scheme->getHTML());

		// create the file
		$source_file = 'settings.html';
		$target_file = 'settings.pdf';
		$head_left = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';
		$this->htmlCreator->initIndexTemplate();
		$this->writeFile($source_file, $this->htmlCreator->build(
			$head_left, $this->testObj->getDescription(), $tpl->get()));
		//$this->pdfCreator->addJob($source_file, $target_file, $head_left);
	}

	/**
	 * Add the test questions to the archive
	 */
	protected function handleQuestions()
	{
		$this->makeDir('questions');

		require_once('Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php');
		$type_translations = ilObjQuestionPool::getQuestionTypeTranslations();

		// Title for header in PDFs
		$head_left = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';

		$question_ids = array();
		switch ($this->testObj->getQuestionSetType())
		{
			case ilObjTest::QUESTION_SET_TYPE_FIXED:
				$question_ids = $this->testObj->getQuestions();
				break;
			case ilObjTest::QUESTION_SET_TYPE_DYNAMIC:
				$question_ids = array_keys($this->getUsedQuestionIds());
				break;

			case ilObjTest::QUESTION_SET_TYPE_RANDOM:
				if ($this->settings->random_questions == ilTestArchiveCreatorPlugin::RANDOM_ALL)
				{
					$question_data = $this->testObj->getPotentialRandomTestQuestions();
					foreach ($question_data as $row)
					{
						$question_ids[] = $row['question_id'];
					}
				}
				else
				{
					$question_ids = array_keys($this->getUsedQuestionIds());
				}
		}

		foreach ($question_ids as $question_id)
		{
			$this->htmlCreator->initMainTemplate();
			$question_gui = $this->testObj->createQuestionGUI("", $question_id);

			/** @var assQuestion $question */
			$question = $question_gui->object;
			$head_right = $question->getTitle();

			// add the list entry
			$element = new ilTestArchiveCreatorQuestion($this);
			$element->question_id = $question_id;
			$element->exam_question_id = $this->plugin->buildExamQuestionId($this->testObj, $question_id);
			$element->title = $question->getTitle();
			$element->type = $type_translations[$question->getQuestionType()];
			$element->max_points = $question->getMaximumPoints();
			$this->questions->add($element);

			$question_dir = 'questions/' . $element->getFolderName();
			$this->makeDir($question_dir);

			// create presentation files
			$tpl = $this->plugin->getTemplate('tpl.question.html');
			$tpl->setVariable('QUESTION_ID', $question_id);
			$tpl->setVariable('TITLE', $question->getTitle());
			$tpl->setVariable('CONTENT', $question_gui->getPreview(FALSE));

			$source_file = $question_dir.'/'.$element->getFilePrefix(). '_presentation.html';
			$target_file = $question_dir.'/'.$element->getFilePrefix(). '_presentation.pdf';
			$element->presentation = $target_file;
			$this->writeFile($source_file, $this->htmlCreator->build(
				$head_left, $this->testObj->getDescription(), $tpl->get()));
			$this->pdfCreator->addJob($source_file, $target_file, $head_left, $head_right);

			if ($this->settings->questions_with_best_solution)
            {
                // re-initialize the template and gui for a new generation
                $this->htmlCreator->initMainTemplate();
                $question_gui = $this->testObj->createQuestionGUI("", $question_id);

                // create best solution files
                $tpl = $this->plugin->getTemplate('tpl.question.html');
                $tpl->setVariable('QUESTION_ID', $question_id);
                $tpl->setVariable('TITLE', $question->getTitle());
                $tpl->setVariable('CONTENT', $question_gui->getSolutionOutput(
                    0, null, true, true,
                    false, false, true, false));

                $source_file = $question_dir.'/'.$element->getFilePrefix(). '_best_solution.html';
                $target_file = $question_dir.'/'.$element->getFilePrefix(). '_best_solution.pdf';
                $element->best_solution = $target_file;
                $this->writeFile($source_file, $this->htmlCreator->build(
                    $head_left, $this->testObj->getDescription(), $tpl->get()));
                $this->pdfCreator->addJob($source_file, $target_file, $head_left, $head_right);
            }

			unset($question_gui, $question);
		}
	}

	/**
	 * Add the participant to the archive
	 */
	protected function handleParticipants()
	{
		$this->makeDir('participants');

		/** @var  ilTestEvaluationUserData $userdata */
		$participants = $this->testObj->getUnfilteredEvaluationData()->getParticipants();
		foreach ($participants as $active_id => $userdata)
		{
			if (is_object($userdata) && is_array($userdata->getPasses()))
			{
				$user = new ilObjUser($userdata->getUserID());

				// pass selection
				switch($this->settings->pass_selection)
				{
					case ilTestArchiveCreatorPlugin::PASS_ALL:
						$passes = $userdata->getPasses();
						break;
					case ilTestArchiveCreatorPlugin::PASS_SCORED:
					default:
						$passes = array($userdata->getScoredPassObject());
				}

				foreach ($passes as $passdata)
				{
					if ($passdata instanceof ilTestEvaluationPassData)
					{
						$this->htmlCreator->initMainTemplate();

						$pass = $passdata->getPass();
						$exam_id = $this->plugin->buildExamId($this->testObj, $active_id, $pass);
						$head_left = $this->testObj->getTitle() . ' [' . $exam_id . ']';
						$head_right = $user->getFullname();

						// add the list entry
						$element = new ilTestArchiveCreatorParticipant($this);
						$element->active_id = $active_id;
						$element->firstname = $user->getFirstname();
						$element->lastname = $user->getLastname();
						$element->login = $user->getLogin();
						$element->matriculation = $user->getMatriculation();
						$element->exam_id = $exam_id;
						$element->pass_number = $passdata->getPass() + 1;
						$element->pass_scored = $userdata->getScoredPass() == $passdata->getPass();
						$element->pass_working_time = $passdata->getWorkingTime();
						if (method_exists($this->testObj, 'lookupLastTestPassAccess')) {
						    // ILIAS 5.4.6 and higher
                            $element->pass_finish_date = $this->testObj->lookupLastTestPassAccess($active_id, $passdata->getPass());
                        }
						elseif (method_exists($this->testObj, 'getPassFinishDate')) {
						    // up to ILIAS 5.4.5
                            $element->pass_finish_date = $this->testObj->getPassFinishDate($active_id, $passdata->getPass());
                        }
						$element->pass_reached_points = $passdata->getReachedPoints();

						$this->participants->add($element);

						// create the list of answers
						$result_array = $this->testObj->getTestResult($active_id, $pass);
						$tpl = $this->plugin->getTemplate('tpl.participant.html');

						// test data of the user
						$info = array();
						$info[$this->lng->txt('firstname')] =  $user->getFirstname();
						$info[$this->lng->txt('lastname')] =  $user->getLastname();
						if ($this->config->with_login) {
							$info[$this->lng->txt('login')] =  $user->getLogin();
						}
						if ($this->config->with_matriculation) {
							$info[$this->lng->txt('matriculation')] = $user->getMatriculation();
						}
						$info[$this->lng->txt('email')] =  $user->getEmail();

						$info[$this->plugin->txt('first_visit')] = ilDatePresentation::formatDate(
								new ilDateTime($userdata->getFirstVisit(), IL_CAL_UNIX))
								. ' ('.$userdata->getFirstVisit().')';

						$info[$this->plugin->txt('last_visit')] = ilDatePresentation::formatDate(
									new ilDateTime($userdata->getLastVisit(), IL_CAL_UNIX))
								. ' ('.$userdata->getLastVisit().')';

						$info[$this->plugin->txt('number_passes')] = $userdata->getPassCount();
						$info[$this->plugin->txt('scored_pass')] = $userdata->getScoredPass() + 1;
						$info[$this->plugin->txt('reached_points')] = $userdata->getReached();
						$info[$this->plugin->txt('mark_official')] = $userdata->getMarkOfficial();
						$info[$this->plugin->txt('mark_short')] = $userdata->getMark();
						$info[$this->plugin->txt('final_result')] = $userdata->getPassed() ?
							$this->plugin->txt('passed') : $this->plugin->txt('not_passed');

						foreach ($info as $label => $content)
						{
							$tpl->setCurrentBlock('data_row');
							$tpl->setVariable('LABEL', $label);
							$tpl->setVariable('CONTENT', $content);
							$tpl->parseCurrentBlock();
						}

						// this works for all question set types
						$questions = $this->getPassQuestionData($active_id, $pass);

						foreach ($questions as $row)
						{
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
							$percent = preg_replace('/100\.00/', '100', $row['percent']);
							$tpl->setVariable('REACHED_PERCENT', $percent);
							$tpl->setVariable('MANUAL', $row['manual'] ? $this->lng->txt('yes') : $this->lng->txt('no'));
							$tpl->parseCurrentBlock();


							// answer and solution output
							$question_gui = $this->testObj->createQuestionGUI($row['type_tag'], $row['qid']);
							$html_answer = $question_gui->getSolutionOutput($active_id, $pass, TRUE, FALSE, FALSE, FALSE, FALSE);

							if ($this->settings->answers_with_best_solution)
							{
								$html_solution = $question_gui->getSolutionOutput($active_id, $pass, FALSE, TRUE, FALSE, FALSE, TRUE);
							}

							//manual feedback
							if (!empty($row['manualFeedback']))
							{
								include_once("./Services/RTE/classes/class.ilRTE.php");
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
							$tpl->setVariable('HTML_ANSWER',$html_answer);

							if ($this->settings->answers_with_best_solution)
							{
								$tpl->setVariable('TXT_BEST_SOLUTION', $this->plugin->txt('question_best_solution'));
								$tpl->setVariable('HTML_SOLUTION',$html_solution);
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
						$tpl->setVariable('PASS_FINISH_DATE', ilDatePresentation::formatDate(new ilDateTime($element->pass_finish_date, IL_CAL_UNIX)));

						// prepare the file structure
						$participant_dir = 'participants/' . $element->getFolderName();
						$source_file = $participant_dir.'/'.$element->getFilePrefix(). '_answers.html';
						$target_file = $participant_dir.'/'.$element->getFilePrefix(). '_answers.pdf';
						$element->answers_file = $target_file;

						// generate the pdf
						$this->makeDir($participant_dir);
						$this->writeFile($source_file, $this->htmlCreator->build(
							$head_left, $this->testObj->getDescription(), $tpl->get()));
						$this->pdfCreator->addJob($source_file, $target_file, $head_left, $head_right);
					}
				}
			}
		}
	}

	/**
	 * Write solution pdf
	 */
	protected function handleSolution()
	{
		//file_put_contents($this->workdir."/solution.pdf",file_get_contents('https://ilias.example.com/ilias.php?ref_id=72&pdf=1&cmd=print&cmdClass=ilobjtestgui&baseClass=ilrepositorygui'));
		global $DIC;
		$log = $DIC->logger();
		$tpl = $DIC['tpl'];
		//$log->pdfg()->debug();
		ilPDFGeneratorUtils::prepareGenerationRequest("Test", PDF_PRINT_VIEW_QUESTIONS);
		$template = new ilTemplate("tpl.il_as_tst_print_test_confirm.html", TRUE, TRUE, "Modules/Test");
		$print_date = mktime(date("H"), date("i"), date("s"), date("m")  , date("d"), date("Y"));
		$max_points= 0;
		$counter = 1;

		require_once 'Modules/Test/classes/class.ilTestQuestionHeaderBlockBuilder.php';
		$questionHeaderBlockBuilder = new ilTestQuestionHeaderBlockBuilder($this->lng);
		$questionHeaderBlockBuilder->setHeaderMode($this->testObj->getTitleOutput());

		if($isPdfDeliveryRequest)
		{
			require_once 'Services/WebAccessChecker/classes/class.ilWACSignedPath.php';
			ilWACSignedPath::setTokenMaxLifetimeInSeconds(60);
		}

		foreach ($this->testObj->questions as $question)
		{
			$template->setCurrentBlock("question");
			$question_gui = $this->testObj->createQuestionGUI("", $question);

			if( $isPdfDeliveryRequest )
			{
				$question_gui->setRenderPurpose(assQuestionGUI::RENDER_PURPOSE_PRINT_PDF);
			}

			$questionHeaderBlockBuilder->setQuestionTitle($question_gui->object->getTitle());
			$questionHeaderBlockBuilder->setQuestionPoints($question_gui->object->getMaximumPoints());
			$questionHeaderBlockBuilder->setQuestionPosition($counter);
			$template->setVariable("QUESTION_HEADER", $questionHeaderBlockBuilder->getHTML());

			$template->setVariable("TXT_QUESTION_ID", $this->lng->txt('question_id_short'));
			$template->setVariable("QUESTION_ID", $question_gui->object->getId());
			$result_output = $question_gui->getSolutionOutput("", NULL, FALSE, TRUE, FALSE, $this->testObj->getShowSolutionFeedback());
			$template->setVariable("SOLUTION_OUTPUT", $result_output);
			$template->parseCurrentBlock("question");
			$counter ++;
			$max_points += $question_gui->object->getMaximumPoints();
		}

		$template->setVariable("TITLE", ilUtil::prepareFormOutput($this->testObj->getTitle()));
		$template->setVariable("PRINT_TEST", ilUtil::prepareFormOutput($this->lng->txt("tst_print")));
		$template->setVariable("TXT_PRINT_DATE", ilUtil::prepareFormOutput($this->lng->txt("date")));
		$template->setVariable("VALUE_PRINT_DATE", ilUtil::prepareFormOutput(strftime("%c",$print_date)));
		$template->setVariable("TXT_MAXIMUM_POINTS", ilUtil::prepareFormOutput($this->lng->txt("tst_maximum_points")));
		$template->setVariable("VALUE_MAXIMUM_POINTS", ilUtil::prepareFormOutput($max_points));
		require_once 'Modules/Test/classes/class.ilTestPDFGenerator.php';
		ilTestPDFGenerator::generatePDF($template->get(), ilTestPDFGenerator::PDF_OUTPUT_FILE, $this->workdir . '/solution.pdf', PDF_PRINT_VIEW_QUESTIONS);
	}

	/**
	 * Write the index files for questions, participants and settings
	 */
	protected function writeIndexFiles()
	{

		// Title for header in files
		$title = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';

		/** @var ilTestArchiveCreatorParticipant $participant */
		foreach ($this->participants->elements as $participant) {
			$participant->answers_hash = @sha1_file($this->workdir. '/'. $participant->answers_file);
		}

		// questions
        if ($this->settings->include_questions)
        {
            $index_file = 'questions.csv';
            $source_file = 'questions.html';
            $target_file = 'questions.pdf';
            $this->writeFile($index_file, $this->questions->getCSV($this->testObj));
            $this->htmlCreator->initIndexTemplate();
            $this->writeFile($source_file, $this->htmlCreator->build(
                $title, $this->testObj->getDescription(), $this->questions->getHTML()));
            // $this->pdfCreator->addJob($source_file, $target_file, $title);
        }

		// participants
        if ($this->settings->include_answers)
        {
            $index_file = 'participants.csv';
            $source_file = 'participants.html';
            $target_file = 'participants.pdf';
            $this->writeFile($index_file, $this->participants->getCSV($this->testObj));
            $this->htmlCreator->initIndexTemplate();
            $this->writeFile($source_file, $this->htmlCreator->build(
                $title, $this->testObj->getDescription(), $this->participants->getHTML()));
            // $this->pdfCreator->addJob($source_file, $target_file, $title);
        }

    // $this->pdfCreator->generateJobs();
    // $this->pdfCreator->clearJobs();
	}


    /**
     * Get the used question ids
     * The array is also filled in handleParticipants()
     * It will be filled here if participants are not included
     *
     * @return array    id => true
     */
    protected function getUsedQuestionIds()
    {
        if (!is_array($this->usedQuestionIds))
        {
            $this->usedQuestionIds = array();
            $participants = $this->testObj->getUnfilteredEvaluationData()->getParticipants();

            /** @var  ilTestEvaluationUserData $userdata */
            foreach ($participants as $active_id => $userdata)
            {
                if (is_object($userdata) && is_array($userdata->getPasses()))
                {
                    switch($this->settings->pass_selection)
                    {
                        case ilTestArchiveCreatorPlugin::PASS_ALL:
                            $passes = $userdata->getPasses();
                            break;
                        case ilTestArchiveCreatorPlugin::PASS_SCORED:
                        default:
                            $passes = array($userdata->getScoredPassObject());
                    }

                    foreach ($passes as $passdata)
                    {
                        if ($passdata instanceof ilTestEvaluationPassData)
                        {
                            $pass = $passdata->getPass();
                            $questions = $this->getPassQuestionData($active_id, $pass);
                            foreach ($questions as $row)
                            {
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
	 * Get the pass question data for a dynamic test
	 * @param	int		$active_id
	 * @param	int		$pass
	 * @return array
	 */
	protected function getPassQuestionData($active_id, $pass)
	{
		global $DIC;
		$ilDB = $DIC->database();


		$questions = array();
		$result_array = $this->testObj->getTestResult($active_id, $pass, false);
		foreach($result_array as $key => $data)
		{
			if($key === 'test' || $key === 'pass')
			{
				continue;
			}

			$question = array();
			$question_id = $data['qid'];
			$question['qid'] = $data['qid'];
			$question['title'] = $data['title'];
			$question["type_tag"] = $data['type_tag'];
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
		$result = $ilDB->queryF($query,array('integer', 'integer'), array($active_id, $pass));
		while ($row = $ilDB->fetchAssoc($result))
		{
			if (isset($questions[$row['question_fi']])) {
				$questions[$row['question_fi']]['manual'] = 1;
			}
		}
		$query = "SELECT question_fi, feedback FROM tst_manual_fb WHERE active_fi = %s AND pass = %s";
		$result = $ilDB->queryF($query,array('integer', 'integer'), array($active_id, $pass));
		while ($row = $ilDB->fetchAssoc($result))
		{
			if (isset($questions[$row['question_fi']])) {
				$questions[$row['question_fi']]['manualFeedback'] = $row['feedback'];
			}
		}

		// Sort the pass question data by sequence
		$sorted_questions = array();
		foreach ($questions as $question)
		{
			$key = sprintf('%09d', (int) $question['nr']).sprintf('%09d', (int) $question['qid']);
			$sorted_questions[$key] = $question;
		}
		ksort($sorted_questions);

		return array_values($sorted_questions);
	}

	/**
	 * Create a sub directory of the working directory
	 * @param string $directory
	 */
	protected function makeDir($directory)
	{
		if (!is_dir($this->workdir .'/'. $directory))
		{
			ilUtil::makeDir($this->workdir .'/'. $directory);
		}
	}

	/**
	 * Write a file to the working dir
	 * @param string $path
	 * @param string $content
	 */
	protected function writeFile($path, $content)
	{
		file_put_contents($this->workdir.'/'.$path, $content);
	}

	/**
	 * Sanitize a file name
	 * @see http://www.house6.com/blog/?p=83
	 * @param string $f
	 * @param string $a_space_replace
	 * @return mixed|string
	 */
	public function sanitizeFilename($f, $a_space_replace = '_') {
		// a combination of various methods
		// we don't want to convert html entities, or do any url encoding
		// we want to retain the "essence" of the original file name, if possible
		// char replace table found at:
		// http://www.php.net/manual/en/function.strtr.php#98669
		$replace_chars = array(
			'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'Ae',
			'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
			'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'Oe', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
			'Û'=>'U', 'Ü'=>'Ue', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'ae', 'ä'=>'a',
			'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
			'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'oe', 'ø'=>'o', 'ù'=>'u',
			'ü'=>'ue', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
		);
		$f = strtr($f, $replace_chars);
		// convert & to "and", @ to "at", and # to "number"
		$f = preg_replace(array('/[\&]/', '/[\@]/', '/[\#]/'), array('-and-', '-at-', '-number-'), $f);
		$f = preg_replace('/[^(\x20-\x7F)]*/','', $f); // removes any special chars we missed
		$f = str_replace(' ', $a_space_replace, $f); // convert space to hyphen
		$f = str_replace("'", '', $f); 	// removes single apostrophes
		$f = str_replace('"', '', $f);  // removes double apostrophes
		$f = preg_replace('/[^\w\-\.\,_ ]+/', '', $f); // remove non-word chars (leaving hyphens and periods)
		$f = preg_replace('/[\-]+/', '-', $f); // converts groups of hyphens into one
		$f = preg_replace('/[_]+/', '_', $f); // converts groups of dashes into one
		return $f;
	}
}
