# TestArchiveCreator

Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
GPLv3, see LICENSE

Author: Fred Neumann <fred.neumann@gmx.de>


This plugin for the LMS ILIAS allows the creation of zipped archives with PDF files for written tests.

INSTALLATION
------------

1. Put the content of the plugin directory in a subdirectory under your ILIAS main directory:
Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TestArchiveCreator

2. Open ILIAS > Administration > Plugins

3. Update/Activate the plugin

4. Open the plugin configuration

5. Enter the server path to an executable of PhantomJS
   (see http://phantomjs.org)


USAGE
-----

1. Mover to the tab "Export" in the test.
3. Click "Settings" in the toolbar to change some properties of the archive creation.
2. Click the button "Create" in the toolbar to create a zipped archive.

The archive containes separate PDF files for the questions in the test and the test runs of participants.
Overviews are written as csv html files.
