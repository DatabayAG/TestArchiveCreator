<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE



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
    ): void
	{
		switch ($a_part)
		{
			//case 'tabs':
			case 'sub_tabs':

				// must be done here because ctrl and tabs are not initialized for all calls
				global $DIC;
				$this->ctrl = $DIC->ctrl();
				$this->tabs = $DIC->tabs();

				// Export tab is shown
				if ($this->ctrl->getCmdClass() == 'iltestexportgui')
				{
					$this->saveTabs('iltestexportgui');
					$this->modifyExportToolbar();
				}

				// Archive Creator settings are shown
				if ($this->ctrl->getCmdClass()  == 'iltestarchivecreatorsettingsgui')
				{
					$this->restoreTabs('iltestexportgui');
				}

				break;

			default:
				break;
		}
	}

	/**
	 * Save the tabs for reuse on the plugin pages
	 * @param string context for which the tabs should be saved
	 */
	protected function saveTabs($a_context)
	{
		$_SESSION['TestArchiveCreator'][$a_context]['TabTarget'] = $this->tabs->target;
		$_SESSION['TestArchiveCreator'][$a_context]['TabSubTarget'] = $this->tabs->sub_target;
	}

	/**
	 * Restore the tabs for reuse on the plugin pages
	 * @param string context for which the tabs should be saved
	 */
	protected function restoreTabs($a_context)
	{
		// reuse the tabs that were saved from the parent gui
		if (isset($_SESSION['TestArchiveCreator'][$a_context]['TabTarget']))
		{
			$this->tabs->target = $_SESSION['TestArchiveCreator'][$a_context]['TabTarget'];
		}
		if (isset($_SESSION['TestArchiveCreator'][$a_context]['TabSubTarget']))
		{
			$this->tabs->sub_target = $_SESSION['TestArchiveCreator'][$a_context]['TabSubTarget'];
		}

		if ($a_context == 'iltestexportgui')
		{
			if (!empty($this->tabs->target)) {
				foreach ($this->tabs->target as $td)
				{
					if (strpos(strtolower($td['link']),'iltestexportgui') !== false)
					{
						// this works when done in handler for the sub_tabs
						// because the tabs are rendered after the sub tabs
						$this->tabs->activateTab($td['id']);
					}
				}
			}
		}
	}

	/**
	 * Modify the toolbar of the meta data editor
	 */
	protected function modifyExportToolbar()
	{
		$gui = new ilTestArchiveCreatorSettingsGUI();
		$gui->modifyExportToolbar();
	}
}
