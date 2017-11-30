<?php


class ilTestArchiveCreatorHTML
{

	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;

	/**
	 * constructor.
	 * @param $plugin
	 * @param $settings
	 */
	public function __construct($plugin, $settings) {
		$this->plugin = $plugin;
		$this->settings = $settings;
	}


	public function build($content)
	{
		$tpl = $this->plugin->getTemplate('tpl.html_file.html');

		$css = file_get_contents(ilUtil::getStyleSheetLocation('filesystem'))	. "\n"			// Delos
			.file_get_contents('Modules/Test/templates/default/ta.css')	. "\n"			// Test
			. file_get_contents($this->plugin->getDirectory().'/css/test_phantomjs.css');	// PDF

		$tpl->setVariable('BASE', ILIAS_HTTP_PATH);
		$tpl->setVariable('CSS', $css);
		$tpl->setVariable('CONTENT', $content);

		return $tpl->get();
	}

}