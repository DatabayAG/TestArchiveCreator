<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilTestArchiveCreatorPDF
{
	/** @var ilTestArchiveCreatorPlugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings */
	public $settings;

	/** @var ilTestArchiveCreatorConfig */
	public $config;

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
		$this->config = $this->plugin->getConfig();
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
		];
		$this->jobs[] = $job;

		if ($this->config->render_twice)
		{
            $this->jobs[] = $job;
        }
		return $job;
	}

	/**
	 * Generate the added batch files as PDF in one step
	 * PDF rendering is done at this step
	 */
	public function generateJobs()
	{
		global $DIC;
		$log = $DIC->logger();

		if (empty($this->jobs))
        {
            return;
        }

		$phantomJs = $this->config->phantomjs_path;
		$scriptFile = $this->plugin->getDirectory() . '/js/doPhantomJobs.js';
		$jobsFile = $this->workdir . '/' . $this->jobsid . '.json';

		$content = [
		    'clientId' => CLIENT_ID,
            'sessionId' => session_id(),
            'cookieDomain' => $_SERVER['HTTP_HOST'],
            'cookiePath' => IL_COOKIE_PATH,
            'cookieSecure' => IL_COOKIE_SECURE,
            'cookieHttpOnly' => IL_COOKIE_HTTPONLY,
            'orientation' => $this->settings->orientation,
            'minRenderingWait' => $this->config->min_rendering_wait,
            'maxRenderingWait' => $this->config->max_rendering_wait,

            'jobs' => $this->jobs
        ];

		file_put_contents($jobsFile, json_encode($content));

		$jobinfo = print_r($content, true);
		$log->root()->debug($jobinfo);

		if (is_executable($phantomJs))
		{
			$command = $phantomJs;
			if ($this->config->any_ssl_protocol)
			{
				$command .= ' --ssl-protocol=any';
			}
			if ($this->config->ignore_ssl_errors)
			{
				$command .= ' --ignore-ssl-errors=true';
			}
			$command .= ' ' . $scriptFile . ' ' . $jobsFile;

			try
			{
				$log->root()->info($command);
				$output = exec($command);
				$log->root()->info($output);
			}
			catch (Exception $e)
			{
				$log->root()->warning($e->getMessage());
			}
		}
		else
		{
			$log->root()->warning("$phantomJs is not executable");
		}
	}

	/**
	 * Remove the job files and clear the variables
	 */
	public function clearJobs()
	{
	    if (!($this->config->keep_creation_directory && $this->config->keep_jobfile)) {
            @unlink($this->workdir . '/' . $this->jobsid . '.json');
        }

		$this->jobs = [];
		$this->jobsid = '';
	}
}