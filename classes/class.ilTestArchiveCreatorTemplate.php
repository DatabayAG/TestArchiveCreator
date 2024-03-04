<?php

/**
 * Class ilTestArchiveCreatorTemplate
 */
class ilTestArchiveCreatorTemplate extends ilGlobalTemplate
{
    public function getDataFrom(ilTestArchiveCreatorTemplate $tpl) : void
    {
        $this->js_files = $tpl->js_files;
        $this->js_files_vp = $tpl->js_files_vp;
        $this->js_files_batch = $tpl->js_files_batch;
        $this->css_files = $tpl->css_files;
    }

	/**
	 * Remove the embedding of the mediaelement player
	 */
	public function removeMediaPlayer() : void
	{
		$jsPaths = ilPlayerUtil::getJsFilePaths();
		foreach ($this->js_files as $index => $file) {
			if (in_array($file, $jsPaths)) {
				unset($this->js_files[$index]);
			}
		}

		$cssPaths = ilPlayerUtil::getCssFilePaths();
		foreach ($this->css_files as $index => $filedef) {
			if (in_array($filedef['file'], $cssPaths)) {
				unset($this->css_files[$index]);
			}
		}
	}
}