<?php

/**
 * Class ilTestArchiveCreatorTemplate
 * @uses ilTemplate
 */
/* class ilTestArchiveCreatorTemplate extends ilTemplate */
//https://github.com/ILIAS-eLearning/ILIAS/commit/04ac77df7c8f04b03b7abc85c3f447b2c4df1f11#diff-37ad6c723e0df7f90af7d648621698255049e73693ecc54f2ba71c56ecb55331
class ilTestArchiveCreatorTemplate extends ilGlobalTemplate 
{
	/**
     * Fill in the inline css
     *
     * @param boolean $a_force
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