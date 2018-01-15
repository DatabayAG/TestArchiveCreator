<?php


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


	/** @var  ilTemplate $tpl */
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
		$this->tpl =  new ilTemplate("tpl.main.html", true, true);
		$GLOBALS['tpl'] = $this->tpl;
		$this->tpl_type = 'main';

		$this->tpl->setVariable('BASE', ILIAS_HTTP_PATH . '/index.html');
		$this->tpl->setVariable("LOCATION_STYLESHEET",ilUtil::getStyleSheetLocation());
		$this->tpl->addCss($this->testObj->getTestStyleLocation("output"), "screen");

		$css = $this->plugin->getDirectory().'/css/test_pdf.css';
		$this->tpl->setCurrentBlock('HeadContent');
		$this->tpl->setVariable('CONTENT_BLOCK', '<link rel="stylesheet" type="text/css" href="'.$css.'" />');
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
			$this->tpl->fillCssFiles();
			$this->tpl->fillInlineCss();
			$this->tpl->fillContentStyle();
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