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
		$title = $this->testObj->getTitle() . ' [' . $this->buildExamId() . ']';

		foreach ($this->testObj->getQuestions() as $question_id)
		{
			/** @var AssQuestionGUI $question_gui */
			$question_gui = $this->testObj->createQuestionGUI("", $question_id);

			/** @var assQuestion $question */
			$question = $question_gui->object;

			// add the list entry
			$element = new ilTestArchiveCreatorQuestion($this);
			$element->question_id = $question_id;
			$element->exam_question_id = $this->buildExamQuestionId($question_id);
			$element->title = $question->getTitle();
			$element->type = $type_translations[$question->getQuestionType()];
			$element->max_points = $question->getMaximumPoints();
			$this->questions->add($element);

			$question_dir = 'questions/' . $element->getFilePrefix();
			$this->makeDir($question_dir);

			// create presentation file
			$tpl = $this->plugin->getTemplate('tpl.question.html');
			$tpl->setVariable('QUESTION_ID', $question_id);
			$tpl->setVariable('TITLE', $question->getTitle());
			$tpl->setVariable('CONTENT', $question_gui->getPreview(FALSE));

			$source_file = $question_dir.'/'.$element->question_id. '_presentation.html';
			$target_file = $question_dir.'/'.$element->question_id. '_presentation.pdf';
			$element->files[$target_file] = $this->plugin->txt('question_presentation');
			$this->writeFile($source_file, $this->htmlCreator->build($tpl->get()));
			$this->pdfCreator->addJob($source_file, $target_file, $title);

			// create best solution file
			$tpl = $this->plugin->getTemplate('tpl.question.html');
			$tpl->setVariable('QUESTION_ID', $question_id);
			$tpl->setVariable('TITLE', $question->getTitle());
			$tpl->setVariable('CONTENT', $question_gui->getSolutionOutput(
				0, null, true, true,
				false, false, true, false));

			$source_file = $question_dir.'/'.$element->question_id. '_best_solution.html';
			$target_file = $question_dir.'/'.$element->question_id. '_best_solution.pdf';
			$element->files[$target_file] = $this->plugin->txt('question_best_solution');
			$this->writeFile($source_file, $this->htmlCreator->build($tpl->get()));
			$this->pdfCreator->addJob($source_file, $target_file, $title);

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
		$participants = $this->testObj->getCompleteEvaluationData(false)->getParticipants();

		require_once 'Modules/Test/classes/class.ilTestResultHeaderLabelBuilder.php';
		$testResultHeaderLabelBuilder = new ilTestResultHeaderLabelBuilder($this->lng, $ilObjDataCache);
		$testResultHeaderLabelBuilder->setTestObjId($this->testObj->getId());
		$testResultHeaderLabelBuilder->setTestRefId($this->testObj->getRefId());

		/** @var  ilTestEvaluationUserData $userdata */
		foreach ($participants as $active_id => $userdata)
		{
			if (is_object($userdata) && is_array($userdata->getPasses()))
			{
				$user = new ilObjUser($userdata->getUserID());
				$testResultHeaderLabelBuilder->setUserId($userdata->getUserID());


				$passes = $userdata->getPasses();
				foreach ($passes as $pass => $passdata)
				{
					$exam_id = $this->buildExamId($active_id, $pass);
					$title = $this->testObj->getTitle() . ' [' . $exam_id . ']';

					// add the list entry
					$element = new ilTestArchiveCreatorParticipant($this);
					$element->active_id = $active_id;
					$element->firstname = $user->getFirstname();
					$element->lastname = $user->getLastname();
					$element->login = $user->getLogin();
					$element->matriculation = $user->getMatriculation();
					$element->exam_id = $exam_id;

					$this->participants->add($element);

					$participant_dir = 'participants/' . $element->getFilePrefix();
					$this->makeDir($participant_dir);

					if (is_object( $passdata ))
					{
						$result_array = $this->testObj->getTestResult($active_id, $pass);

						// create best solution file
						$tpl = $this->plugin->getTemplate('tpl.participant.html');
						$tpl->setVariable('CONTENT', $test_evaluation_gui->getPassListOfAnswers(
							$result_array,
							$active_id, $pass,
							true, false,
							false, true, false, null,
							$testResultHeaderLabelBuilder));

						$source_file = $participant_dir.'/'.$element->exam_id. '_answers.html';
						$target_file = $participant_dir.'/'.$element->exam_id. '_answers.pdf';
						$element->files[$target_file] = $this->plugin->txt('answers');
						$this->writeFile($source_file, $this->htmlCreator->build($tpl->get()));
						$this->pdfCreator->addJob($source_file, $target_file, $title);
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
		$title = $this->testObj->getTitle() . ' [' . $this->buildExamId() . ']';

		// questions
		$index_file = 'questions.csv';
		$source_file = 'questions.html';
		$target_file = 'questions.pdf';
		$this->writeFile($index_file, $this->questions->getCSV($this->testObj));
		$this->writeFile($source_file, $htmlCreator->build($this->questions->getHTML()));
		$this->pdfCreator->addJob($source_file, $target_file, $title);

		// participants
		$index_file = 'participants.csv';
		$source_file = 'participants.html';
		$target_file = 'participants.pdf';
		$this->writeFile($index_file, $this->participants->getCSV($this->testObj));
		$this->writeFile($source_file, $htmlCreator->build($this->participants->getHTML()));
		$this->pdfCreator->addJob($source_file, $target_file, $title);
	}

	/**
	 * Build the exam id and allow ids without active_id and pass
	 * @param null $active_id
	 * @param null $pass
	 * @return string
	 */
	protected function buildExamId($active_id = null, $pass = null)
	{
		global $ilSetting;

		$inst_id = $ilSetting->get( 'inst_id', null );
		$obj_id = $this->testObj->getId();

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
	 * @param $question_id
	 * @return string
	 */
	protected function buildExamQuestionId($question_id)
	{
		return $this->buildExamId(). '_Q' . $question_id;
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