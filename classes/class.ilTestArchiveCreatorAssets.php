<?php

use ILIAS\Filesystem\Filesystem;

class ilTestArchiveCreatorAssets
{
    protected ilTestArchiveCreatorPlugin $plugin;
    protected ilTestArchiveCreatorSettings $settings;
    protected ilTestArchiveCreatorFileSystems $filesystems;

    /** @var Filesystem|null filesystem to store the assets */
    protected ?Filesystem $system;

    /** @var string|null relative path to the assets folder in the file system */
    protected ?string $path;


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
        $this->filesystems = new ilTestArchiveCreatorFileSystems();
        $this->system = $this->filesystems->deriveFilesystemFrom($workdir);
        $this->path = $this->filesystems->createRelativePath($workdir. '/assets') ;
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
            $dom_doc->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

            $result = $xslt->transformToDoc($dom_doc);
            $xml= $result->saveHTML();

            return $xml;
        }
        catch (\Throwable $e) {
            return 'HTML PROCESSING ERROR:<br>' . $e->getMessage() . '<hr>' . $html;
        }
    }

    protected function processStyle(string $css) : string
    {
        // regular expression for url()
    }

    protected function processUrl(string $url) : string
    {
        $parsed = parse_url($url);
        $system = $this->filesystems->deriveFilesystemFrom($parsed['path']);
        $path = $this->filesystems->createRelativePath($parsed['path']);

        if (isset($system) && isset($path)) {
            $info = pathinfo($path);
            $asset = md5($parsed['path']) . '.' . $info['extension'];
            if (!$this->system->has($this->path . '/' . $asset)) {
                $this->system->writeStream($this->path . '/' . $asset, $system->readStream($path));
            }
            return $asset;
        }

        return $url;
    }

}