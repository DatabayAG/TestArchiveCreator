<?php

/**
 * Class ilTestArchiveCreatorTemplate
 */
class ilTestArchiveCreatorTemplate extends ilGlobalTemplate
{
	/**
     * Fill in the inline css
     */
    public function fillInlineCss1()
    {
        if (!$this->blockExists("css_inline")) {
            return;
        }
        foreach ($this->inline_css as $css) {
            $this->setCurrentBlock("css_inline");
            $this->setVariable("CSS_INLINE", $css["css"]);
            $this->parseCurrentBlock();
        }
    }

	/**
     * Fill Content Style
     */
    public function fillNewContentStyle1()
    {
        $this->setVariable(
            "LOCATION_NEWCONTENT_STYLESHEET_TAG",
            '<link rel="stylesheet" type="text/css" href="' .
            ilUtil::getNewContentStyleSheetLocation()
            . '" />'
        );
    }
	/**
	 * Remove the embedding of the mediaelement player
	 */
	public function removeMediaPlayer()
	{
		$jsPaths = ilPlayerUtil::getJsFilePaths();
		foreach ($this->js_files as $index => $file)
		{
			if (in_array($file, $jsPaths))
			{
				unset($this->js_files[$index]);
			}
		}

		$cssPaths = ilPlayerUtil::getCssFilePaths();
		foreach ($this->css_files as $index => $filedef)
		{
			if (in_array($filedef['file'], $cssPaths))
			{
				unset($this->css_files[$index]);
			}
		}
	}
}