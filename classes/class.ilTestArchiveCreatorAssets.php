<?php

use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Exception\FileAlreadyExistsException;
use ILIAS\Filesystem\Exception\FileNotFoundException;
use ILIAS\Filesystem\Exception\IOException;

class ilTestArchiveCreatorAssets
{
    protected ilTestArchiveCreatorFileSystems $filesystems;
    protected ilTestArchiveCreatorList $assets;
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

    /**
     * Constructor
     * @param string $workdir storage of working directory for the archive creation
     * @param string $assets_url url for loading assets for PDF generation
     */
    public function __construct(ilTestArchiveCreatorList $assets, string $workdir, string $assets_url)
    {
        $this->filesystems = new ilTestArchiveCreatorFileSystems();
        $this->storage = $this->filesystems->getPureStorage();
        $this->assets = $assets;

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
     * Process HTML code with XSLT
     * This will replace URLs in attributes line 'src' with the function process URL
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
            return $processed;
        }
        catch (\Throwable $e) {
            throw $e;
            return 'HTML PROCESSING ERROR:<br>' . $e->getMessage() . '<hr>' . $html;
        }
    }

    /**
     * Process the URLs found in a css style sheet
     *
     * @param string $css           css code to be processed
     * @param string $url_path      path of the css file relative to the ilias directory
     * @param bool $in_asset        css code is already in an asset file that is copied to the archive
     * @return string               the processed css code
     */
    protected function processStyle(string $css, string $url_path, bool $in_asset = true) : string
    {
        // get the prefix for relative urls
        $info = pathinfo($url_path);
        $prefix = $info['dirname'] ?? '';

        // find and replace the contents of url() expressions in the css code
        if (preg_match_all('/url\s*\(([^)]*)\)/', $css, $matches)) {
            if (isset($matches[1])) {
                foreach ($matches[1] as $url) {
                    // remove quotation and whitespaces
                    $new = str_replace('\'','', $url);
                    $new = str_replace('"','', $new);
                    $new= trim($url);

                    // make relative to the ilias directory
                    $new = './' . $this->filesystems->removeDots($prefix . '/' .$new);

                    // replaced with processed url
                    $new = $this->processUrl($new, $in_asset);
                    $css = str_replace($url, $new, $css);
                }
            }
        }
        return $css;
    }

    /**
     * Process url found in HTML or CSS
     *
     * @param string $url               URL to be processed
     * @param bool $in_asset            URL is already in an asset file, target will be copied to the same directory
     * @return string                   Offline URL to the asset folder in the archive or online URL to the asset delivery script
     */
    protected function processUrl(string $url, bool $in_asset = false) : string
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
                    $content = null;

                    // process urls in the asset content
                    if ($extension == 'css') {
                        $content = $this->processStyle($system->read($path), $parsed['path'], true);
                    }

                    if ($this->copy_assets
                        && !$this->storage->has($this->storage_path . '/' . $asset_name)
                        && !$this->storage->has($this->storage_path . '/' . $sec_name)) {
                        if (isset($content)) {
                            $this->storage->write($this->storage_path . '/' . $asset_name, $content);
                        } else {
                            $this->storage->writeStream($this->storage_path . '/' . $asset_name, $system->readStream($path));
                        }
                    }

                    $asset = new ilTestArchiveCreatorAsset($this->assets->creator);
                    $asset->asset_name = $asset_name;
                    $asset->original_url = $url;
                    if (!$this->assets->has($asset)) {
                        $this->assets->add($asset);
                    }

                    if (!$in_asset || $this->linking_path == $this->assets_url) {
                        // offline link to asset directory or online url to delivery script
                        return $this->linking_path . '/' . $asset_name;
                    }
                    else {
                        // offline link from asset to asset (same directory)
                        return $asset_name;
                    }
                }
            }

        }

        // leave original url if asset can't be processed
        return $url;
    }

    /**
     * Check if an extension is allowed for being copied to the archive
     * PHP files should not be copied
     */
    protected function checkExtension(string $extension) : bool
    {
        $forbidden = ['php'];
        return !in_array(strtolower($extension), $forbidden);
    }
}