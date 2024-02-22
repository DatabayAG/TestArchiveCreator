<?php

use ILIAS\Filesystem\Filesystem;

class ilTestArchiveCreatorAssets
{
    protected ilTestArchiveCreatorPlugin $plugin;
    protected ilTestArchiveCreatorSettings $settings;
    protected ilTestArchiveCreatorFileSystems $filesystems;
    protected Filesystem $storage;

    /** @var string url for loading assets for PDF generation */
    protected string $assets_url;

    /** @var string path to the assets directory in the storage */
    protected string $storage_path;

    /** @var string relative path for linking the assets from a processed file */
    protected string $linking_path = '';

    /** @var int id of the processed test for providing asset urls */
    protected int $obj_id = 0;

    /** @var bool indicator whether assets should be copied */
    protected $copy_assets = false;

    /** @var array for debugging */
    protected $urls = [];

    /**
     * Constructor
     * @param string $workdir storage of working directory for the archive creation
     * @param string $assets_url url for loading assets for PDF generation
     */
    public function __construct(string $workdir, string $assets_url)
    {
        $this->filesystems = new ilTestArchiveCreatorFileSystems();
        $this->storage = $this->filesystems->getPureStorage();
        $this->storage_path = $workdir. '/assets';
        $this->assets_url = $assets_url;
    }

    /**
     * Copy local media as assets to a subdirectory and replace their URLs
     * @param string $html  HTML code to be processed
     * @param string $path  path of the file from which the embedded assets should be linked (relative to working directory)
     * @return string
     */
    public function processForEmbedding(string $html, string $path) : string
    {
        $this->copy_assets = true;
        $this->linking_path = str_repeat('../', substr_count($path, '/')) . 'assets';
        return $this->processXslt($html, __DIR__. '/../templates/assets.xsl');
    }

    /**
     * Replace the URLs of local media to the delivery script
     * @param string $html  HTML code to be processed
     * @return string
     */
    public function processForPdfGeneration(string $html) : string
    {
        $this->linking_path = $this->assets_url;
        return $this->processXslt($html, __DIR__. '/../templates/assets.xsl');
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
            $processed = $result->saveHTML();

//            echo "<pre>";
//            print_r($this->urls);
//            exit;

            //return implode("\n", $this->urls);
            return $processed;
        }
        catch (\Throwable $e) {
            throw $e;
            return 'HTML PROCESSING ERROR:<br>' . $e->getMessage() . '<hr>' . $html;
        }
    }

    protected function processStyle(string $css, $url_path) : string
    {
        // get the prefix for relative urls
        $info = pathinfo($url_path);
        $prefix = $info['dirname'] ?? '';

        // find and replace the urls in css
        if (preg_match_all('/url\s*\(([^)]*)\)/', $css, $matches)) {
            if (isset($matches[1])) {
                foreach ($matches[1] as $url) {
                    $new = str_replace('\'','', $url);
                    $new = str_replace('"','', $new);
                    $new = './' . $this->filesystems->removeDots($prefix . '/' . trim($new));
                    $new = $this->processUrl($new, true);
                    //$this->urls[$prefix . ' / ' . $url] = $new;
                    $css = str_replace($url, $new, $css);
                }
            }
        }
        return $css;
    }

    protected function processUrl(string $url, $in_asset = false) : string
    {
        $parsed = parse_url(str_replace(ILIAS_HTTP_PATH, '.', $url));

        if (isset($parsed['path'])) {
            $system = $this->filesystems->deriveFilesystemFrom($parsed['path']);
            $path = $this->filesystems->createRelativePath($parsed['path']);

            if (isset($system) && isset($path)) {
                $info = pathinfo($path);
                $extension = $info['extension'] ?? '';
                $asset_name = sha1($parsed['path']) . '.' . $extension;
                $sec_name = sha1($parsed['path']) . $extension . '.sec';

                if ($this->checkExtension($info['extension'] ?? '') && $system->has($path) && !$system->hasDir($path)) {

                    if ($this->copy_assets
                        && !$this->storage->has($this->storage_path . '/' . $asset_name)
                        && !$this->storage->has($this->storage_path . '/' . $sec_name)) {
                        if ($extension == 'css') {
                            $css = $this->processStyle($system->read($path), $parsed['path']);
                            $this->storage->write($this->storage_path . '/' . $asset_name, $css);
                        } else {
                            $this->storage->writeStream($this->storage_path . '/' . $asset_name, $system->readStream($path));
                        }
                    }

                    // todo: add asset o the list of assets

                    if (!$in_asset || $this->linking_path == $this->assets_url) {
                        // local url in html or pdf generation
                        return $this->linking_path . '/' . $asset_name;
                    }
                    else {
                        // local access from asset to asset (same directory)
                        return $asset_name;
                    }
                }
            }

        }

        // leave original url if asset can't be processed
        return $url;
    }

    /**
     * Check if an extension is allowed
     */
    protected function checkExtension(string $extension) : bool
    {
        $forbidden = ['php'];
        return !in_array(strtolower($extension), $forbidden);
    }
}