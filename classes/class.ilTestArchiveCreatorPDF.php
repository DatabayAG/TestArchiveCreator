<?php


class ilTestArchiveCreatorPDF
{
	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;


	/**
	 * @var string Path to PhantomJS
	 */
//	public $phantomJsPath = '/opt/phantomjs/phantomjs-1.9.8';
	public $phantomJsPath = '/opt/phantomjs/phantomjs-2.1.1';

	/**
	 * @var string Path to PhantomJS
	 */
	public $phantomJsZoomFactor = 1;



	/**
	 * @var array [ ['sourceUrl' => string,
	 *                'targetFile' => string,
	 *                'targetName' =>  string ] ... ]
	 */
	protected $jobs = [];

	/**
	 * @var string
	 */
	protected $workdir;

	/**
	 * @var string job number
	 */
	protected $jobsid = '';

	/**
	 * @var string time for the footer
	 */
	protected $time;

	/**
	 * constructor.
	 * @param $plugin
	 * @param $settings
	 */
	public function __construct($plugin, $settings, $workdir) {
		$this->plugin = $plugin;
		$this->settings = $settings;
		$this->workdir = $workdir;

		ilDatePresentation::setUseRelativeDates(false);
		$this->time = ilDatePresentation::formatDate(new ilDateTime(time(), IL_CAL_UNIX));
	}


	/**
	 * Add the current report as batch file for the PDF genration
	 * HTML rendering is done at this step
	 *
	 * @param string    $sourceFile
	 * @param string	$targetFile
	 * @param string	$headLeft
	 * @param string	$headRight
	 * @return array    job data
	 */
	public function addJob($sourceFile, $targetFile, $headLeft = '', $headRight = '')
	{

		if (empty($this->jobsid)) {
			$this->jobsid = date('Y-m-d_H-i-s_') . (string)rand(0, 9999);
		}

		$job = [
			'sourceFile' => $this->workdir.'/'.$sourceFile,      // file must exist
			'targetFile' => $this->workdir.'/'.$targetFile,
			'headLeft' => $headLeft,
			'headRight' => $headRight,
			'footLeft' => $this->plugin->txt('label_generated') . ' '. $this->time,
			'zoomFactor' => $this->phantomJsZoomFactor
		];
		$this->jobs[] = $job;
		return $job;
	}

	/**
	 * Generate the added batch files as PDF in one step
	 * PDF rendering is done at this step
	 */
	public function generateJobs()
	{
		$phantomJs = $this->phantomJsPath;
		$scriptFile = $this->plugin->getDirectory() . '/js/doPhantomJobs.js';
		$jobsFile = $this->workdir . '/' . $this->jobsid . '.json';

		file_put_contents($jobsFile, json_encode($this->jobs));
		if (is_executable($phantomJs)) {
			exec("$phantomJs $scriptFile $jobsFile");
		}
	}

	/**
	 * Remove the job files and clear the variables
	 */
	public function clearJobs()
	{
		@unlink($this->workdir . '/' . $this->jobsid . '.json');

		$this->jobs = [];
		$this->jobsid = '';
	}
}