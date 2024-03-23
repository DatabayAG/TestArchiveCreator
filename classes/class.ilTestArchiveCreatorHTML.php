<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilTestArchiveCreatorHTML
{
	public ilTestArchiveCreatorPlugin $plugin;
	public ilTestArchiveCreatorConfig $config;
    public ilTestArchiveCreatorSettings $settings;
	protected ilTestArchiveCreatorTemplate$tpl;


	/**
	 * constructor.
	 */
	public function __construct(
        ilTestArchiveCreatorPlugin $plugin,
        ilTestArchiveCreatorSettings $settings
    ) {
		$this->plugin = $plugin;
        $this->settings = $settings;
		$this->config = $plugin->getConfig();
		$this->initMainTemplate();
	}

	/**
	 * Init an own version of the main ilias template with a content page template file
     * It is used by the question GUI to register css and js files
	 * This should be done always before a question or participant file is rendered
	 */
	public function initMainTemplate() : void
	{
		// we need to rewrite the main template
		$this->tpl = new ilTestArchiveCreatorTemplate($this->plugin->getDirectory(). "/templates/tpl.content_page.html", true, true);
		$GLOBALS['tpl'] = $this->tpl;

        // render all MathJax at once in buildContent at the end
        ilMathJax::getInstance()->init(ilMathJax::PURPOSE_DEFERRED_PDF);
    }

    /**
     * Build an index page
     * This does not need the main template
     */
    public function buildIndex(string $title = '', string $description = '', string $content = '') : string
    {
        $tpl = $this->plugin->getTemplate('tpl.index_page.html');
        $tpl->setVariable('TITLE', $title);
        $tpl->setVariable('DESCRIPTION', $description);
        $tpl->setVariable('CONTENT', $content);
        return $tpl->get();
    }


	/**
	 * Build a content page
     * This uses a new instance of the content page template with collected css and js files from the main template
     * The function can be called twice for HTML and PDF outout after processing the content
     *
     * @see ilLMPresentationGUI::page()
	 */
	public function buildContent(string $title = '', string $description = '', string $content = '', bool $for_pdf = false) : string
	{
        // allow separate building for HTML and PDF based on the same main template after content is rendered with it
        $tpl = new ilTestArchiveCreatorTemplate($this->plugin->getDirectory(). "/templates/tpl.content_page.html", true, true);
        $tpl->getDataFrom($this->tpl);


        // Inclusion of MathJax script to the template is needed for STACK questions
        // if server-side rendering is not enabled for browser

        if ($for_pdf) {
            $tpl->removeMediaPlayer();
            $content = ilMathJax::getInstance()
                                  ->init(ilMathJax::PURPOSE_PDF)
                                  ->setRendering(ilMathJax::RENDER_SVG_AS_XML_EMBED)
                                  ->includeMathJax($tpl)
                                  ->insertLatexImages($content);
        }
        else {
            $content = ilMathJax::getInstance()
                                ->init(ilMathJax::PURPOSE_EXPORT)
                                ->setRendering(ilMathJax::RENDER_SVG_AS_XML_EMBED)
                                ->includeMathJax($tpl)
                                ->insertLatexImages($content);
        }

        $tpl->addCss(ilUtil::getStyleSheetLocation('output', 'test_javascript.css', 'Modules/TestQuestionPool'), 'all');
        $tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"),'print');
        $tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_pdf.css", "Modules/Test"),'print');

        $tpl->fillContentLanguage();
        $tpl->fillCssFiles();
        $tpl->fillJavaScriptFiles();
        $tpl->fillOnLoadCode();

        $tpl->setVariable('HEAD_TITLE', $title);
        if ($for_pdf || !$this->config->embed_assets) {
            $tpl->setVariable('BASE', ILIAS_HTTP_PATH . '/index.html');
        }

        // specific content styles, see ilPortfolioPageGUI
        $tpl->setVariable("LOCATION_ADDITIONAL_STYLESHEET", ilObjStyleSheet::getPlaceHolderStylePath());
        $tpl->setVariable("LOCATION_SYNTAX_STYLESHEET", ilObjStyleSheet::getSyntaxStylePath());

        // system style
        // inclusion is optional for phantomjs
        // web fonts may produce large pdf files with unselectable text
        if (!$for_pdf
            || $this->config->pdf_engine != ilTestArchiveCreatorConfig::ENGINE_PHANTOM
            || $this->plugin->getConfig()->use_system_styles) {
            $tpl->setVariable("LOCATION_STYLESHEET",ilUtil::getStyleSheetLocation());
        }

        // content styles
        // add the stylesheet of the plugin as last one
        $content_styles = [
            ilObjStyleSheet::getContentStylePath(0),
            ilUtil::getNewContentStyleSheetLocation(),
            ilObjStyleSheet::getContentPrintStyle(),
            './' . $this->plugin->getDirectory().'/templates/archive.css'
        ];

        foreach ( $content_styles as $style) {
            $tpl->setCurrentBlock('ContentStyle');
            $tpl->setVariable("LOCATION_CONTENT_STYLESHEET", $style);
            $tpl->parseCurrentBlock();
        }

        // zoom factor of the body
        $tpl->setVariable('ZOOM', sprintf('style="zoom:%s;"', $this->settings->zoom_factor));

        // fill the body
		if (!empty($title)) {
			$tpl->setVariable('TITLE', $title);
		}
		if (!empty($description)) {
            $tpl->setVariable('DESCRIPTION', $description);
		}
		$tpl->setVariable('CONTENT', $content);

		return $tpl->get();
	}
}