<?php

class ilTestArchiveCreatorAssets
{
    const assetpath = '/assets';

    protected ilTestArchiveCreatorPlugin $plugin;
    protected ilTestArchiveCreatorSettings $settings;
    protected string $workdir;

    /**
     * constructor.
     * @param ilTestArchiveCreatorPlugin $plugin
     * @param ilTestArchiveCreatorSettings $settings
     * @param string $workir
     */
    public function __construct(ilTestArchiveCreatorPlugin $plugin, ilTestArchiveCreatorSettings $settings, string $workdir)
    {
        $this->plugin = $plugin;
        $this->settings = $settings;
        $this->workdir = $workdir;

    }

    /**
     * copy local media as assets to a subdirectory and replace their URLs
     * @param string $content
     * @return string
     */
    public function handleContent(string $html) : string
    {
        return $this->processXslt($html, __DIR__. '/../xsl/assets.xsl');
    }

    /**
     * Process html code with XSLT
     * The process_version is a number which can be increased with a new version of the processing
     * This number is provided as a parameter to the XSLT processing
     */
    protected function processXslt(string $html, string $xslt_file) : string
    {
        try {
            // get the xslt document
            // set the URI to allow document() within the XSL file
            $xslt_doc = new \DOMDocument('1.0', 'UTF-8');
            $xslt_doc->loadXML(file_get_contents($xslt_file));
            $xslt_doc->documentURI = $xslt_file;

            // get the xslt processor
            $xslt = new \XSLTProcessor();
            $xslt->registerPhpFunctions();
            $xslt->importStyleSheet($xslt_doc);

            // get the html document
            $dom_doc = new \DOMDocument('1.0', 'UTF-8');
            $dom_doc->loadHTML('<?xml encoding="UTF-8"?'.'>'. $html);

            //$xml = $xslt->transformToXml($dom_doc);
            $result = $xslt->transformToDoc($dom_doc);
            $xml= $result->saveHTML();

            $xml = preg_replace('/<\?xml.*\?>/', '', $xml);
            $xml = str_replace( ' xmlns:php="http://php.net/xsl"', '', $xml);

            return $xml;
        }
        catch (\Throwable $e) {
            return 'HTML PROCESSING ERROR:<br>' . $e->getMessage() . '<hr>' . $html;
        }
    }

    protected function processUrl(string $url) : string
    {
        return $url . '#fred';
    }


    /**
     * Check if an asset url is local
     */
    protected function isLocalUrl(string $url) : bool
    {

    }
}