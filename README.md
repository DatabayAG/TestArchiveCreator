# TestArchiveCreator

Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
GPLv3, see LICENSE

Author: Fred Neumann <fred.neumann@ili.fau.de>


This plugin for the LMS ILIAS open source allows the creation of zipped archives with PDF files for written tests.
It requires an installation of PhantomJS on the ILIAS server.
http://phantomjs.org


INSTALLATION
------------

1. Put the content of the plugin directory in a subdirectory under your ILIAS main directory:
Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TestArchiveCreator

2. Open ILIAS > Administration > Plugins

3. Update/Activate the plugin

4. Open the plugin configuration

5. Enter the server path to an executable of PhantomJS

USAGE
-----

1. Mover to the tab "Export" in the test.

3. Click "Settings" in the toolbar to change some properties of the archive creation.

2. Click the button "Create" in the toolbar to create a zipped archive.

The archive containes separate PDF files for the questions in the test and the test runs of participants.
Overviews are written as csv html files.

PLANNED CREATION
----------------

Archive creation may take a long time for large tests. For this reason the plugin allows to configure
a planned creation of the archive in each test. This requires two additional setups.

You need to set up a call of the ILIAS cron jobs on your web server, see the ILIAS installation guide:
https://www.ilias.de/docu/goto_docu_pg_8240_367.html

Additionally, you need to install the cron job plugin TestArchiveCron:
https://github.com/ilifau/TestArchiveCron

1. Install and activate this plugin.

2. Go to Administration > General Settings > Cron Jobs

3. Activate the 'Test Archive Creation' job

4. Set a reasonable schedule for the job, e.h. hourly.

Now you can set a time in the settings of the archive creation. When the cron job is called the time is due, it
will create the archive.


VERSIONS
--------

1.0.1 for ILIAS 5.2 (2018-01-18)
 - cron job support