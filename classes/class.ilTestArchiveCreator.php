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

	/** @var  string absolute path of the working directory */
	protected $workdir;

	/** @var  ilTestArchiveCreatorList $questions */
	protected $questions;

	/** @var ilTestArchiveCreatorList $participants */
	protected $participants;

	/** @var ilTestArchiveCreatorList $properties */
	protected $properties;


	/**
	 * Constructor
	 * @param ilTestArchiveCreatorPlugin $plugin
	 * @param int $obj_id test object id
	 */
	public function __construct($plugin, $obj_id)
	{
		global $DIC;

		$this->db = $DIC->database();

		$this->plugin = $plugin;
		$this->settings = $plugin->getSettings($obj_id);

		require_once ('Modules/Test/classes/class.ilObjTest.php');
		$this->testObj = new ilObjTest($obj_id, false);
	}


	public function createArchive()
	{
		$this->initCreation();

		$this->handleQuestions();
		$this->handleParticipants();
		$this->handleProperties();

		$this->writeIndexFiles();
		$this->finishCreation();
	}


	protected function initCreation()
	{
		$this->workdir = CLIENT_DATA_DIR . '/tst_data/archive_plugin/tst_'.$this->testObj->getId();
		ilUtil::delDir($this->workdir);
		ilUtil::makeDirParents($this->workdir);

		$this->plugin->includeClass('models/class.ilTestArchiveCreatorElement.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorList.php');
		$this->plugin->includeClass('models/class.ilTestArchiveCreatorQuestion.php');

		$this->questions = new ilTestArchiveCreatorList($this, new ilTestArchiveCreatorQuestion($this));
		$this->questions->setTitle($this->plugin->txt('questions'));
	}


	protected function finishCreation()
	{
		$export_dir = CLIENT_DATA_DIR . '/tst_data/archive_exports/tst_'.$this->testObj->getId();
		ilUtil::makeDirParents($export_dir);

		$zipfile = 'test_archive_obj_'.$this->testObj->getId().'_'.time().'_plugin';
		ilUtil::zip($this->workdir, $export_dir .'/'. $zipfile, true);
	}


	protected function handleProperties()
	{

	}

	protected function handleQuestions()
	{
		ilUtil::makeDirParents($this->workdir . '/questions');

		require_once('Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php');
		$type_translations = ilObjQuestionPool::getQuestionTypeTranslations();

		foreach ($this->testObj->getQuestions() as $question_id)
		{
			/** @var AssQuestionGUI $question_gui */
			$question_gui = $this->testObj->createQuestionGUI("", $question_id);

			/** @var assQuestion $question */
			$question = $question_gui->object;

			$element = new ilTestArchiveCreatorQuestion($this);
			$element->question_id = $question_id;
			$element->title = $question->getTitle();
			$element->type = $type_translations[$question->getQuestionType()];
			$element->max_points = $question->getMaximumPoints();
			$this->questions->add($element);


			unset($question_gui, $question);
		}
	}

	protected function handleParticipants()
	{

	}

	protected function writeIndexFiles()
	{
		$questions_html = $this->questions->getHTML();
		file_put_contents($this->workdir.'/questions.html', $questions_html);
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