<?php

use \Spatie\Browsershot\Browsershot;

class ilTestArchiveCreatorBrowsershot extends ilTestArchiveCreatorPDF
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
                $html = file_get_contents($job['sourceFile']);

                $engine = Browsershot::html($html)
                    ->setNodeModulePath($this->config->bs_node_module_path)
                    ->setChromePath($this->config->bs_chrome_path)
                    ->setNodeBinary($this->config->bs_node_path)
                    ->setNpmBinary($this->config->bs_npm_path)
                    ->waitUntilNetworkIdle(true)
                    ->format('A4')
                    ->margins(20,10,20,10,'mm')
                    ->showBrowserHeaderAndFooter();

                if ($this->config->ignore_ssl_errors) {
                    $engine->ignoreHttpsErrors();
                }

                if ($this->settings->orientation == ilTestArchiveCreatorPlugin::ORIENTATION_LANDSCAPE) {
                    $engine->landscape(true);
                } else {
                    $engine->landscape(false);
                }

                if (!empty($this->settings->zoom_factor)) {
                    $engine->scale($this->settings->zoom_factor);
                }

                $header = $job['headLeft'] ?? '';
                $footer = $job['footLeft'] ?? '';

                $engine->headerHtml(
                    '<p style="font-size:5px; padding-left:30px; margin-top:-5px;">'
                    . $header
                    .'</p>');
                $engine->footerHtml(
                    '<p style="font-size:5px; padding-left:30px;margin-top:5px;">' .
                        '<span class="pageNumber"></span> / <span class="totalPages"></span> - '
                        . $footer
                        . '</p>'
                );
                $engine->save($job['targetFile']);
            }
        }
        catch (Exception $e)
        {
            throw $e;
            $this->logger->warning($e->getMessage());
        }
    }
}