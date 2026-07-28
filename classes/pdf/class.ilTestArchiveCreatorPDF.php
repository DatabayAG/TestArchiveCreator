<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\DI\Container;
use ILIAS\Filesystem\Util\LegacyPathHelper;

abstract class ilTestArchiveCreatorPDF
{
    public Container $dic;
    public ilLogger $logger;
    public ILIAS\Filesystem\Filesystem $temp;

    public ilTestArchiveCreatorPlugin $plugin;
    public ilTestArchiveCreatorSettings $settings;
    public ilTestArchiveCreatorConfig $config;

    /**
     * @var array [ ['sourceUrl' => string,
     *                'targetFile' => string,
     *                'targetName' =>  string ] ... ]
     */
    protected array $jobs = [];

    /** @var string working directory (relative path in the temp filesystem)  */
    protected string $workdir;

    /** @var string job number */
    protected string $jobsid = '';

    /** @var string time for the footer  */
    protected string $time;

    /** @var array pdf */
    protected array $failed_files = [];

    /**
     * constructor.
     * @param string $workdir   working directory (relative path in the temp filesystem)
     */
    public function __construct(ilTestArchiveCreatorPlugin $plugin, ilTestArchiveCreatorSettings $settings, string $workdir)
    {
        global $DIC;

        $this->dic = $DIC;
        $this->logger = $DIC->logger()->root();
        $this->temp = (new ilTestArchiveCreatorFileSystems())->getPureTemp();

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
     * @param string    $sourceFile     path of source file relative to the working directory
     * @param string	$targetFile     path of target file relative to the working directory
     * @param string	$headLeft       left content of the PDF header
     * @param string	$headRight      right content of the PDF header
     * @return array    job data
     */
    public function addJob(string $sourceFile, string $targetFile, string $headLeft = '', string $headRight = ''): array
    {

        if (empty($this->jobsid)) {
            $this->jobsid = date('Y-m-d_H-i-s_') . rand(0, 9999);
        }

        $job = [
            'sourceFile' => CLIENT_DATA_DIR . '/temp/' . $this->workdir . '/' . $sourceFile,      // file must exist
            'targetFile' => CLIENT_DATA_DIR . '/temp/' . $this->workdir . '/' . $targetFile,
            'headLeft' => $headLeft,
            'headRight' => $headRight,
            'footLeft' => $this->plugin->txt('label_generated') . ' ' . $this->time,
        ];
        $this->jobs[] = $job;
        return $job;
    }

    /**
     * Get the file to which the jos information should be written
     */
    protected function getJobsFile(): string
    {
        return $this->workdir . '/' . $this->jobsid . '.json';
    }

    /**
     * Generate the added batch files as PDF in one-step
     * PDF rendering is done at this step
     */
    abstract public function generateJobs(): void;

    /**
     * Remove the job files and clear the variables
     */
    public function clearJobs(): void
    {
        if (!($this->config->keep_jobfile)) {
            foreach ($this->jobs as $job) {
                // path was made absolute in addJob
                if ($this->temp->has(LegacyPathHelper::createRelativePath($job['sourceFile']))) {
                    $this->temp->delete(LegacyPathHelper::createRelativePath($job['sourceFile']));
                }
            }
            if ($this->temp->has($this->getJobsFile())) {
                $this->temp->delete($this->getJobsFile());
            }
        }

        $this->jobs = [];
        $this->jobsid = '';
        $this->failed_files = [];
    }

    public function getFailedFiles()
    {
        return $this->failed_files;
    }
}
