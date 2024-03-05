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
                $header = $job['headLeft'] ?? '';
                $footer = $job['footLeft'] ?? '';

                $post = [
                    'html' => file_get_contents($job['sourceFile']),
                    'format' => 'A4',
                    'landscape' => $this->settings->orientation == ilTestArchiveCreatorPlugin::ORIENTATION_LANDSCAPE,
                    'headerTemplate' => '<p style="font-size:10px; padding-left:60px; margin-top:-5px;">'
                                                        . $header .'</p>',
                    'footerTemplate' => '<p style="font-size:10px; padding-left:60px;margin-top:5px;">'
                                                        .'<span class="pageNumber"></span> / <span class="totalPages"></span> - '
                                                        . $footer . '</p>'
                ];

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
                $curlConnection->setOpt(CURLOPT_POSTFIELDS, $this->urlencodeAssoc($post));
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
            $this->logger->warning($e->getMessage());
        }
    }

    /**
     * Url encode an array of parameters
     */
    protected function urlencodeAssoc(array $assoc) :string
    {
        $parts = [];
        foreach ($assoc as $key => $value) {
            $parts[] = urlencode($key) . '=' . urlencode($value);
        }
        return implode('&', $parts);
    }
}