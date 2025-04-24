<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\Test\Presentation\TabsManager;

/**
 * User interface hook class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilTestArchiveCreatorUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilCtrl $ctrl */
    protected $ctrl;

    /** @var ilTabsGUI $tabs */
    protected $tabs;

    /** @var  ilTestArchiveCreatorPlugin $plugin_object */
    protected ?ilUserInterfaceHookPlugin $plugin_object = null;

    /**
     * Modify GUI objects, before they generate ouput
     *
     * @param string $a_comp component
     * @param string $a_part string that identifies the part of the UI that is handled
     * @param array $a_par array of parameters (depend on $a_comp and $a_part)
     */
    public function modifyGUI(
        string $a_comp,
        string $a_part,
        array $a_par = array()
    ): void {
        switch ($a_part) {

            case 'tabs':

                // must be done here because ctrl and tabs are not initialized for all calls
                global $DIC;
                $this->ctrl = $DIC->ctrl();
                $this->tabs = $DIC->tabs();

                // Export tab is shown
                if ($this->ctrl->getCmdClass() == strtolower(ilTestExportGUI::class)) {
                    ilSession::set('TestArchiveCreatorTabs', $this->tabs->target);
                    $this->tabs->activateTab(TabsManager::TAB_ID_EXPORT);
                    $gui = new ilTestArchiveCreatorSettingsGUI();
                    $gui->modifyExportToolbar();

                }

                // Archive Creator settings are shown
                if ($this->ctrl->getCmdClass() == strtolower(ilTestArchiveCreatorSettingsGUI::class)) {
                    if (ilSession::has('TestArchiveCreatorTabs')) {
                        $this->tabs->target = (array) ilSession::get('TestArchiveCreatorTabs');
                        $this->tabs->activateTab(TabsManager::TAB_ID_EXPORT);
                    }
                }
                break;

            default:
                break;
        }
    }
}
