# TestArchiveCreator

Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
GPLv3, see LICENSE

Author: Fred Neumann <fred.neumann@ili.fau.de>

This plugin for the LMS ILIAS open source allows the creation of zipped archives with PDF files for written tests.
It requires an installation of PhantomJS on the ILIAS server.
http://phantomjs.org

Please look at the PhantomJS web site for general installation instructions. Short hint for Debian based systems (thanks to Rachid Rabah):
    "apt-get install phantomjs"
PhantomJS will be located in "/user/bin/phantomjs"
Set the following environment variables:
    "export QT_QPA_PLATFORM=offscreen"
    "export QT_QPA_FONTDIR=/usr/share/fonts/truetype"
If you need a font with Japanese characters:
    "apt-get install fonts-takao"

PLUGIN INSTALLATION
-------------------

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
1.1.0 for ILIAS 5.2 and 5.3 (2018-05-07)
- compatibility for ILIAS 5.3
- fixed output of question ids on console when run by cron
- added an index.html to the archive

1.0.3 for ILIAS 5.2 (2018-02-08)
- new config setting to keep the creation directory after zipping
- logging of the phantomjs command line with INFO level

1.0.2 for ILIAS 5.2 (2018-01-31)
- logging of phantomjs calls
- jobfile content is logged with DEBUG level
- phantomjs console message is logged with INFO level
- not executable phantomjs or exceptions are logged with WARNING level

1.0.1 for ILIAS 5.2 (2018-01-18)
 - cron job support