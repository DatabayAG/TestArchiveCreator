<?php

class ilTestArchiveCreatorServer extends ilTestArchiveCreatorPDF
{
    /**
     * Generate the added batch files as PDF in one-step
     * PDF rendering is done at this step
     */
    public function generateJobs() : void
    {
        if (empty($this->jobs))
        {
            return;
        }

        try {
            foreach ($this->jobs as $job) {
                $post = [];
                $post[] = 'html=' . urlencode(file_get_contents($job['sourceFile']));
                $post[] = 'format=' . urlencode('A4');

                $curlConnection = new ilCurlConnection($this->config->server_url);
                $curlConnection->init();
                $proxy = ilProxySettings::_getInstance();
                if ($proxy->isActive()) {
                    $curlConnection->setOpt(CURLOPT_HTTPPROXYTUNNEL, true);
                    if (!empty($proxy->getHost())) {
                        $curlConnection->setOpt(CURLOPT_PROXY, $proxy->getHost());
                    }
                    if (!empty($proxy->getPort())) {
                        $curlConnection->setOpt(CURLOPT_PROXYPORT, $proxy->getPort());
                    }
                }
                $curlConnection->setOpt(CURLOPT_RETURNTRANSFER, true);
                $curlConnection->setOpt(CURLOPT_VERBOSE, false);
                $curlConnection->setOpt(CURLOPT_TIMEOUT, 60);
                $curlConnection->setOpt(CURLOPT_POST, 1);
                $curlConnection->setOpt(CURLOPT_POSTFIELDS, implode('&', $post));
                $curlConnection->setOpt(CURLOPT_HTTPHEADER, array(
                    "cache-control: no-cache",
                    "content-type: application/x-www-form-urlencoded"
                ));

                $pdf = $curlConnection->exec();

                file_put_contents($job['targetFile'], $pdf);
            }
        }
        catch (Exception $e)
        {
            throw $e;
            $this->logger->warning($e->getMessage());
        }
    }
}