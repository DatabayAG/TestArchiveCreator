<?php


class ilTestArchiveCreatorHTML
{

	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;

	/** @var  string base tag for the header */
	public $base;

	/** @var  ilObjectTest */
	public $testObj;

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

	public function addTestInfo($testObj)
	{
		$this->testObj = $testObj;
	}

	public function build($content)
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
		$tpl->setVariable('CONTENT', $content);

		// test info should be added
		if (isset($this->testObj))
		{

		}

		return $tpl->get();
	}

}