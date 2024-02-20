<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilTestArchiveCreatorHTML
{
	/** @var ilTestArchiveCreatorPlugin $plugin */
	public $plugin;

	/** @var ilTestArchiveCreatorSettings $settings */
	public $settings;

	/** @var  ilTestArchiveCreatorTemplate $tpl */
	protected $tpl;


	/**
	 * constructor.
	 */
	public function __construct(
        ilTestArchiveCreatorPlugin $plugin,
        ilTestArchiveCreatorSettings $settings) {
		$this->plugin = $plugin;
		$this->settings = $settings;
		$this->initMainTemplate();
	}


	/**
	 * Init the main ilias template
	 * This should be done always before a question or participant file is rendered
	 */
	public function initMainTemplate()
	{
		// we need to rewrite the main template
		$this->tpl =  new ilTestArchiveCreatorTemplate($this->plugin->getDirectory(). "/templates/tpl.content_page.html", true, true);
		$GLOBALS['tpl'] = $this->tpl;

		$this->tpl->setVariable('BASE', ILIAS_HTTP_PATH . '/index.html');
		if ($this->plugin->getConfig()->use_system_styles)
		{
			$this->tpl->setVariable("LOCATION_STYLESHEET",ilUtil::getStyleSheetLocation());
		}
        $this->tpl->addCss(ilUtil::getStyleSheetLocation('filesystem', 'test_javascript.css', 'Modules/TestQuestionPool'), 'all');
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("filesystem", "test_print.css", "Modules/Test"),'all');
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("filesystem", "test_pdf.css", "Modules/Test"),'all');

		$css = file_get_contents($this->plugin->getDirectory().'/templates/tpl.styles.html');
		$css = str_replace('BODY_ZOOM', $this->settings->zoom_factor, $css);

        $this->tpl->setCurrentBlock('HeadContent');
        $this->tpl->setVariable('CONTENT_BLOCK', $css);
        $this->tpl->parseCurrentBlock();

		ilMathJax::getInstance()->init(ilMathJax::PURPOSE_PDF)
			->setRendering(ilMathJax::RENDER_SVG_AS_XML_EMBED);
	}

    /**
     * Build an index page
     */
    public function buildIndex(string $title = '', string $description = '', string $content = '')
    {
        $tpl = $this->plugin->getTemplate('tpl.index_page.html');
        $this->tpl->setVariable('TITLE', $title);
        $this->tpl->setVariable('DESCRIPTION', $description);
        $this->tpl->setVariable('CONTENT', $content);
        return $tpl->get();
    }


	/**
	 * Build a contentPage
     * This uses the
	 * @param string $title
	 * @param string $description
	 * @param string $content
	 * @return string
     *
     * @see ilLMPresentationGUI::page()
	 */
	public function buildContent(string $title = '', string $description = '', string$content = '')
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