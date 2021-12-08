<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilTestArchiveCreatorHTML
{
	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;

	/** @var ilObjTest */
	public $testObj;

	/** @var  string base tag for the header */
	public $base;


	/** @var  ilTestArchiveCreatorTemplate $tpl */
	protected $tpl;

	/** @var string $tpl_type */
	public $tpl_type = 'index';


	/**
	 * constructor.
	 * @param $plugin
	 * @param $settings
	 * @param $base
	 */
	public function __construct($plugin, $settings, $testObj) {
		$this->plugin = $plugin;
		$this->settings = $settings;
		$this->testObj = $testObj;
		$this->initMainTemplate();
	}


	/**
	 * Init the main ilias template
	 * This should be done always before a question or participant file is rendered
	 */
	public function initMainTemplate()
	{
		// we need to rewrite the main template
		$this->plugin->includeClass('class.ilTestArchiveCreatorTemplate.php');
		$this->tpl =  new ilTestArchiveCreatorTemplate($this->plugin->getDirectory(). "/templates/tpl.main.html", true, true);
		$GLOBALS['tpl'] = $this->tpl;
		$this->tpl_type = 'main';

		$this->tpl->setVariable('BASE', ILIAS_HTTP_PATH . '/index.html');
		if ($this->plugin->getConfig()->use_system_styles)
		{
			$this->tpl->setVariable("LOCATION_STYLESHEET",ilUtil::getStyleSheetLocation());
		}
		$this->tpl->addCss($this->testObj->getTestStyleLocation("output"), "all");
        $this->tpl->addCss(ilUtil::getStyleSheetLocation('filesystem', 'test_javascript.css', 'Modules/TestQuestionPool'), 'all');
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("filesystem", "test_print.css", "Modules/Test"),'all');
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("filesystem", "test_pdf.css", "Modules/Test"),'all');

		$css = file_get_contents($this->plugin->getDirectory().'/templates/tpl.styles.html');
		$css = str_replace('BODY_ZOOM', $this->settings->zoom_factor, $css);

        $this->tpl->setCurrentBlock('HeadContent');
        $this->tpl->setVariable('CONTENT_BLOCK', $css);
        $this->tpl->parseCurrentBlock();

		require_once('Services/MathJax/classes/class.ilMathJax.php');
		ilMathJax::getInstance()->init(ilMathJax::PURPOSE_PDF)
			->setRendering(ilMathJax::RENDER_SVG_AS_XML_EMBED);
	}

	/**
	 * Init the template for index files
	 */
	public function initIndexTemplate()
	{
		$this->tpl = $this->plugin->getTemplate('tpl.index.html');
		$this->tpl_type = 'index';
	}


	/**
	 * Build am HTML file
	 * @param string $title
	 * @param string $description
	 * @param string $content
	 * @return string
	 */
	public function build($title = '', $description = '', $content = '')
	{
		if ($this->tpl_type == 'main')
		{
			$this->tpl->removeMediaPlayer();

			$this->tpl->fillCssFiles();
			// $this->tpl->fillInlineCss(); method is private
			// $this->tpl->fillContentStyle(); method is private
			$this->tpl->fillInlineCss1();
			$this->tpl->fillNewContentStyle1();

			$this->tpl->fillBodyClass();

			$this->tpl->fillJavaScriptFiles();
			$this->tpl->fillOnLoadCode();
		}

		$html = '';
		if (!empty($title))
		{
			$html = "<h1>". $title ."</h1>\n";
		}
		if (!empty($description))
		{
			$html .= "<p>". $description ."</p>\n";
		}
		$html .= $content;

		$this->tpl->setVariable('CONTENT', $html);
		return $this->tpl->get();
	}
}