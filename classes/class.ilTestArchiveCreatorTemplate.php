<?php

/**
 * Class ilTestArchiveCreatorTemplate
 * @uses ilTemplate
 */
class ilTestArchiveCreatorTemplate extends ilTemplate
{
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