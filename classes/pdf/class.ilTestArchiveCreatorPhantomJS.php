<?php

class ilTestArchiveCreatorPhantomJS extends ilTestArchiveCreatorPDF
{
    /**
     * Generate the added batch files as PDF in one step
     * PDF rendering is done at this step
     */
    public function generateJobs()
    {
        if (empty($this->jobs))
        {
            return;
        }

        $phantomJs = $this->config->phantomjs_path;
        $scriptFile = $this->plugin->getDirectory() . '/js/doPhantomJobs.js';
        $jobsFile = $this->getJobsFile();

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
        $this->logger->debug($jobinfo);

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
            // thanks to Stefan Schneider
            if (ilProxySettings::_getInstance()->isActive()) {
                $command .= ' --proxy='.ilProxySettings::_getInstance()->getHost() . ':' . ilProxySettings::_getInstance()->getPort();
            }

            $command .= ' ' . $scriptFile . ' ' . $jobsFile;

            try
            {
                $this->logger->info($command);
                $output = exec($command);
                $this->logger->info($output);
            }
            catch (Exception $e)
            {
                $this->logger->warning($e->getMessage());
            }
        }
        else
        {
            $this->logger->warning("$phantomJs is not executable");
        }
    }
}