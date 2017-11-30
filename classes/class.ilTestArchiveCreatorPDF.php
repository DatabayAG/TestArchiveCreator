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
	protected $jobsPath;

	/**
	 * @var string job number
	 */
	protected $jobsid = '';


	/**
	 * constructor.
	 * @param $plugin
	 * @param $settings
	 */
	public function __construct($plugin, $settings, $jobsPath) {
		$this->plugin = $plugin;
		$this->settings = $settings;
		$this->jobsPath = $jobsPath;
	}


	/**
	 * Add the current report as batch file for the PDF genration
	 * HTML rendering is done at this step
	 *
	 * @param string    $sourceFile
	 * @param string	$targetFile
	 * @return array    job data
	 */
	public function addJob($sourceFile, $targetFile)
	{
		if (empty($this->jobsid)) {
			$this->jobsid = date('Y-m-d_H-i-s_') . (string)rand(0, 9999);
		}

		$job = [
			'sourceFile' => $sourceFile,        					// file must exist
			'targetFile' => $targetFile,
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
		$jobsFile = $this->jobsPath . '/' . $this->jobsid . '.json';

		file_put_contents($jobsFile, json_encode($this->jobs));
		if (is_executable($phantomJs)) {
			exec("$phantomJs $scriptFile $jobsFile");
		}
	}


	public function clearJobs()
	{
//		foreach ($this->jobs as $job) {
//			@unlink($job['sourceFile']);
//			@unlink($job['targetFile']);
//		}
		@unlink($this->jobsPath . '/' . $this->jobsid . '.json');

		$this->jobs = [];
		$this->jobsid = '';
	}
}