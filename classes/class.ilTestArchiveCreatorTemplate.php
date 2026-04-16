<?php

/**
 * Class ilTestArchiveCreatorTemplate
 */
class ilTestArchiveCreatorTemplate extends ilGlobalTemplate
{
    public function getDataFrom(ilTestArchiveCreatorTemplate $tpl): void
    {
        $this->js_files = $tpl->js_files;
        $this->js_files_vp = $tpl->js_files_vp;
        $this->js_files_batch = $tpl->js_files_batch;
        $this->css_files = $tpl->css_files;
        $this->on_load_code = $tpl->on_load_code;
    }
}
