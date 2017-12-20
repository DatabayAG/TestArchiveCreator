<?php

/**
 * Creation of test archives
 */
class ilTestArchiveCreator
{
	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

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

	/** @var array id => title */
	protected $question_titles = array();

	/** @var ilTestArchiveCreatorList $participants */
	protected $participants;

	/** @var ilTestArchiveCreatorList $properties */
	protected $properties;

	/** @var  ilTestArchiveCreatorPDF */
	protected $pdfCreator;

	/** @var  ilTestArchiveCreatorHTML */
	protected $htmlCreator;

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
		$this->settings = $plugin->getSettings($obj_id);

		require_once ('Modules/Test/classes/class.ilObjTest.php');
		$this->testObj = new ilObjTest($obj_id, false);
	}


	/**
	 * Create the archive
	 */
	public function createArchive()
	{
		$this->initCreation();

		$this->handleQuestions();
		$this->handleParticipants();
		$this->handleProperties();

		$this->writeIndexFiles();
		$this->finishCreation();
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

		$this->htmlCreator = new ilTestArchiveCreatorHTML($this->plugin, $this->settings, ILIAS_HTTP_PATH);
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
		$this->pdfCreator->generateJobs();
		$this->pdfCreator->clearJobs();

		$export_dir = CLIENT_DATA_DIR . '/tst_data/archive_exports/tst_'.$this->testObj->getId();
		ilUtil::makeDirParents($export_dir);

		$zipfile = 'test_archive_obj_'.$this->testObj->getId().'_'.time().'_plugin';
		ilUtil::zip($this->workdir, $export_dir .'/'. $zipfile, true);
	}

	/**
	 * Add the test properties to the archiv
	 */
	protected function handleProperties()
	{

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

		foreach ($this->testObj->getQuestions() as $question_id)
		{
			/** @var AssQuestionGUI $question_gui */
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
			$this->question_titles[$question_id] = $question->getTitle();

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

			unset($question_gui, $question);
		}
	}

	/**
	 * Add the participant to the archive
	 */
	protected function handleParticipants()
	{
		global $DIC;
		$ilObjDataCache = $DIC['ilObjDataCache'];

		$this->makeDir('participants');

		require_once './Modules/Test/classes/class.ilTestEvaluationGUI.php';
		$test_evaluation_gui = new ilTestEvaluationGUI($this->testObj);

		require_once 'Modules/Test/classes/class.ilTestResultHeaderLabelBuilder.php';
		$testResultHeaderLabelBuilder = new ilTestResultHeaderLabelBuilder($this->lng, $ilObjDataCache);
		$testResultHeaderLabelBuilder->setTestObjId($this->testObj->getId());
		$testResultHeaderLabelBuilder->setTestRefId($this->testObj->getRefId());


		/** @var  ilTestEvaluationUserData $userdata */
		$participants = $this->testObj->getUnfilteredEvaluationData()->getParticipants();
		foreach ($participants as $active_id => $userdata)
		{
			if (is_object($userdata) && is_array($userdata->getPasses()))
			{
				$user = new ilObjUser($userdata->getUserID());
				$testResultHeaderLabelBuilder->setUserId($userdata->getUserID());

				// pass selection
				switch($this->settings->pass_selection)
				{
					case ilTestArchiveCreatorSettings::PASS_ALL:
						$passes = $userdata->getPasses();
						break;
					case ilTestArchiveCreatorSettings::PASS_SCORED:
					default:
						$passes = array($userdata->getScoredPassObject());
				}

				foreach ($passes as $passdata)
				{
					if ($passdata instanceof ilTestEvaluationPassData)
					{
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
						$element->pass_finish_date = $this->testObj->getPassFinishDate($active_id, $passdata->getPass());
						$element->pass_reached_points = $passdata->getReachedPoints();

						$this->participants->add($element);

						// create the list of answers
						$result_array = $this->testObj->getTestResult($active_id, $pass);
						$tpl = $this->plugin->getTemplate('tpl.participant.html');

						// test data of the user
						$info = array();
						$info[$this->lng->txt('login')] =  $user->getLogin();
						$info[$this->lng->txt('matriculation')] = $user->getMatriculation();
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

						// pass overview of answers
						foreach ($this->getPassQuestionData($userdata, $passdata) as $row)
						{
							$tpl->setCurrentBlock('question_row');
							$tpl->setVariable('SEQUENCE', $row['sequence']);
							$tpl->setVariable('QUESTION_ID', $row['id']);
							$tpl->setVariable('QUESTION_TITLE', $row['title']);
							$tpl->setVariable('ANSWERED', $row['isAnswered'] ? $this->lng->txt('yes') : $this->lng->txt('no'));
							$tpl->setVariable('MAX_POINTS', $row['points']);
							$tpl->setVariable('REACHED_POINTS', $row['reached']);
							$tpl->setVariable('REACHED_PERCENT', $row['points'] > 0 ?
								round($row['reached'] / $row['points'], 2) : '');
							$tpl->setVariable('MANUAL', $row['manual'] ? $this->lng->txt('yes') : $this->lng->txt('no'));
							$tpl->parseCurrentBlock();
						}

						$tpl->setVariable('TXT_SEQUENCE', $this->lng->txt('tst_question_no'));
						$tpl->setVariable('TXT_QUESTION_ID', $this->lng->txt('question_id'));
						$tpl->setVariable('TXT_QUESTION_TITLE', $this->lng->txt('tst_question_title'));
						$tpl->setVariable('TXT_ANSWERED', $this->plugin->txt('answered'));
						$tpl->setVariable('TXT_MAX_POINTS', $this->lng->txt('tst_maximum_points'));
						$tpl->setVariable('TXT_REACHED_POINTS', $this->lng->txt('tst_reached_points'));
						$tpl->setVariable('TXT_REACHED_PERCENT', $this->lng->txt('tst_percent_solved'));
						$tpl->setVariable('TXT_MANUAL', $this->plugin->txt('manual'));


						// detailed answers
						$tpl->setVariable('USERNAME', $user->getFullname());
						$tpl->setVariable('ANSWERS', $test_evaluation_gui->getPassListOfAnswers(
							$result_array,
							$active_id, $pass,
							true, false,
							false, true, false, null,
							$testResultHeaderLabelBuilder));

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
	 * Write the index files for questions, participants and settings
	 */
	protected function writeIndexFiles()
	{
		// use html creator without base tag
		$htmlCreator = new ilTestArchiveCreatorHTML($this->plugin, $this->settings, '');

		// Title for header in PDFs
		$head_left = $this->testObj->getTitle() . ' [' . $this->plugin->buildExamId($this->testObj) . ']';

		// questions
		$index_file = 'questions.csv';
		$source_file = 'questions.html';
		$target_file = 'questions.pdf';
		$this->writeFile($index_file, $this->questions->getCSV($this->testObj));

		$this->writeFile($source_file, $htmlCreator->build(
			$head_left, $this->testObj->getDescription(), $this->questions->getHTML()));
		$this->pdfCreator->addJob($source_file, $target_file, $head_left);

		// participants
		$index_file = 'participants.csv';
		$source_file = 'participants.html';
		$target_file = 'participants.pdf';
		$this->writeFile($index_file, $this->participants->getCSV($this->testObj));
		$this->writeFile($source_file, $htmlCreator->build(
			$head_left, $this->testObj->getDescription(), $this->participants->getHTML()));
		$this->pdfCreator->addJob($source_file, $target_file, $head_left);
	}

	/**
	 * Get a merged list of pass question data
	 * @param ilTestEvaluationUserData $userdata
	 * @param ilTestEvaluationPassData $passdata
	 * @return array
	 */
	protected function getPassQuestionData($userdata, $passdata)
	{
		$questions = array();

		$user_questions = $userdata->getQuestions($passdata->getPass());
		if (is_array($user_questions))
		{
			foreach ($user_questions as $user_question)
			{
				$question_id = $user_question['id'];
				$question = array();
				$question['id'] = $question_id;
				$question['o_id'] = $user_question['o_id'];
				$question['title'] = $this->question_titles[$user_question['id']];
				$question['sequence'] = $user_question['sequence'];
				$questions[$question_id] = $question;
			}
		}

		$answered_questions = $passdata->getAnsweredQuestions();
		if (is_array($answered_questions))
		{
			foreach ($answered_questions as $answered_question)
			{
				$question_id = $answered_question['id'];
				$question =  $questions[$question_id];
				$question['points'] = $answered_question['points'];
				$question['reached'] = $answered_question['reached'];
				$question['isAnswered'] = $answered_question['isAnswered'];
				$question['manual'] = $answered_question['manual'];
				$questions[$question_id] = $question;
			}
		}

		$sorted_questions = array();
		foreach ($questions as $question)
		{
			$key = sprintf('%09d', (int) $question['sequence']).printf('%09d', (int) $question['id']);
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