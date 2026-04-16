<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


class ilTestArchiveCreatorHTML
{
    public ilTestArchiveCreatorPlugin $plugin;
    public ilTestArchiveCreatorConfig $config;
    public ilTestArchiveCreatorSettings $settings;
    protected ilTestArchiveCreatorTemplate $tpl;


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
    public function initMainTemplate(): void
    {
        // we need to rewrite the main template
        $this->tpl = new ilTestArchiveCreatorTemplate(
            "tpl.content_page.html",
            true,
            true,
            $this->plugin->getModuleForTemplates()
        );
        $GLOBALS['tpl'] = $this->tpl;

        // things that would normally be added by the standard global template or the test output GUI
        iljQueryUtil::initjQuery($this->tpl);
    }

    /**
     * Get the main template
     * Replacing the main template as global variable seems not being enough in all cases
     */
    public function getMainTemplate(): ilGlobalTemplateInterface
    {
        return $this->tpl;
    }

    /**
     * Build an index page
     * This does not need the main template
     */
    public function buildIndex(string $title = '', string $description = '', string $content = ''): string
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
     * The function can be called twice for HTML and PDF output after processing the content
     *
     * @see ilLMPresentationGUI::page()
     */
    public function buildContent(string $title = '', string $description = '', string $content = '', bool $for_pdf = false): string
    {
        // allow separate building for HTML and PDF based on the same main template after content is rendered with it
        $tpl = new ilTestArchiveCreatorTemplate(
            "tpl.content_page.html",
            true,
            true,
            $this->plugin->getModuleForTemplates()
        );
        $tpl->getDataFrom($this->tpl);

        $tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css"), 'print');
        $tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_pdf.css"), 'print');

        $tpl->fillContentLanguage();
        $tpl->fillCssFiles();
        $tpl->fillJavaScriptFiles();
        $tpl->fillOnLoadCode();

        $tpl->setVariable('HEAD_TITLE', $title);
        if ($for_pdf || !$this->config->embed_assets) {
            $tpl->setVariable('BASE', ILIAS_HTTP_PATH . '/index.html');
        }

        // specific content styles, see ilPortfolioPageGUI
        $tpl->setVariable("LOCATION_SYNTAX_STYLESHEET", ilObjStyleSheet::getSyntaxStylePath());
        $tpl->setVariable("LOCATION_STYLESHEET", ilUtil::getStyleSheetLocation());

        // content styles
        // add the stylesheet of the plugin as last one
        $content_styles = [
            ilObjStyleSheet::getContentStylePath(0),
            ilUtil::getNewContentStyleSheetLocation(),
            ilObjStyleSheet::getContentPrintStyle(),
            $this->plugin->getPathInPublic() . '/templates/archive.css'
        ];

        foreach ($content_styles as $style) {
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
