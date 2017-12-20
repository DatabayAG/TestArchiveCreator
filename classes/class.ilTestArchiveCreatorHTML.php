<?php


class ilTestArchiveCreatorHTML
{

	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;

	/** @var  string base tag for the header */
	public $base;

	/**
	 * constructor.
	 * @param $plugin
	 * @param $settings
	 * @param $base
	 */
	public function __construct($plugin, $settings, $base = '') {
		$this->plugin = $plugin;
		$this->settings = $settings;
		$this->base = $base;
	}

	public function build($title = '', $description = '', $content = '')
	{
		$tpl = $this->plugin->getTemplate('tpl.html_file.html');

		$css = file_get_contents(ilUtil::getStyleSheetLocation('filesystem'))	. "\n"			// Delos
			.file_get_contents('Modules/Test/templates/default/ta.css')	. "\n"			// Test
			. file_get_contents($this->plugin->getDirectory().'/css/test_phantomjs.css');	// PDF

		if ($this->base)
		{
			$tpl->setVariable('BASE', $this->base);
		}
		$tpl->setVariable('CSS', $css);

		if (!empty($title))
		{
			$tpl->setVariable('TITLE', $title);
		}

		if (!empty($description))
		{
			$tpl->setVariable('DESCRIPTION', $description);
		}

		$tpl->setVariable('CONTENT', $content);

		return $tpl->get();
	}

}