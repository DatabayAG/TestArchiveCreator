<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


abstract class ilTestArchiveCreatorPDF
{
    /** @var \ILIAS\DI\Container */
    public $dic;

    /** @var ilLogger */
    public $logger;

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
	public function __construct($plugin, $settings, $workdir)
    {
        global $DIC;

        $this->dic = $DIC;
        $this->logger = $DIC->logger()->root();
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

		// replace http(s) urls with file urls
		if ($this->config->pdf_engine == ilTestArchiveCreatorConfig::ENGINE_PHANTOM && $this->config->use_file_urls) {
		    $content = file_get_contents($this->workdir.'/'.$sourceFile);
            $content = str_replace(ILIAS_HTTP_PATH, 'file://'. ILIAS_ABSOLUTE_PATH, $content);

            // temporary source file will be deleted in clearJobs()
		    $sourceFile .= '.temp.html';
            file_put_contents( $this->workdir.'/'.$sourceFile, $content);
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
     * Get the file to which the jos information should be written
     */
    protected function getJobsFile() : string
    {
        return $this->workdir . '/' . $this->jobsid . '.json';
    }

	/**
	 * Generate the added batch files as PDF in one step
	 * PDF rendering is done at this step
	 */
	abstract public function generateJobs();

	/**
	 * Remove the job files and clear the variables
	 */
	public function clearJobs()
	{
	    if (!($this->config->keep_creation_directory && $this->config->keep_jobfile)) {
            @unlink($this->workdir . '/' . $this->jobsid . '.json');
        }

	    // delete the temporary source files with file urls (see addJob)
	    if ($this->config->pdf_engine == ilTestArchiveCreatorConfig::ENGINE_PHANTOM && $this->config->use_file_urls) {
	        foreach ($this->jobs as $job) {
                @unlink($job['sourceFile']);
            }
        }

		$this->jobs = [];
		$this->jobsid = '';
	}
}