# TestArchiveCreator

Copyright (c) 2017-2019 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
GPLv3, see LICENSE

Author: Fred Neumann <fred.neumann@ili.fau.de>

This plugin for the LMS ILIAS open source allows the creation of zipped archives with PDF files for written tests.
It requires an installation of PhantomJS on the ILIAS server.
http://phantomjs.org

Please look at the PhantomJS web site for general installation instructions. Short hint for Debian based systems (thanks to Rachid Rabah):
    `apt-get install phantomjs`
PhantomJS will be located in `/user/bin/phantomjs`
Set the following environment variables:
    `export QT_QPA_PLATFORM=offscreen`
    `export QT_QPA_FONTDIR=/usr/share/fonts/truetype`
If you need a font with Japanese characters:
    `apt-get install fonts-takao`


You may also take binary distribution fom https://bitbucket.org/ariya/phantomjs/downloads/

* PhantomJS 2.1.1 is the preferred one, but renders web fons as graphis which results in large, unsearchable PDFs.
You may prevent this by deactivating 'Use System Styles' in the plugin configuration, but this leads to ugly output.

* PhantomJS 1.9.8 is able to render web fonts, so 'Use System Styles' can be activated. But this older version seems to have
some problems with ssl and images, so you should set 'Any SSL Protocoll', 'Ignore SSL Errors', and 'Render Twice" in the
plugin configuration.


Plugin installation
-------------------

1. Put the content of the plugin directory in a subdirectory under your ILIAS main directory:
Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TestArchiveCreator
2. Open ILIAS > Administration > Plugins
3. Update/Activate the plugin
4. Open the plugin configuration
5. Edit the plugin configuration and enter at least the server path to an executable of PhantomJS


Usage
-----

1. Mover to the tab "Export" in the test.
3. Click "Settings" in the toolbar to change some properties of the archive creation.
2. Click the button "Create" in the toolbar to create a zipped archive.

The archive containes separate PDF files for the questions in the test and the test runs of participants.
Overviews are written as csv html files.

Planned Creation
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

Using with Web Access Checker
-----------------------------

Using the plugin with an activated ILIAS web access checker (WAC) may cause missing images in the PDF files.
ILIAS 5.2 does not sign all images for the WAC and the valid time of the signature may be too short for the rendering jobs
of large archives. In this case the WAC tries to determine the access based on the user session. The plugin Version 1.2 provides
the session cookie for PhantomJs, but the session based check of the WAC may take too long for the rendering timeout.
A call from the TestArchiveCron plugin does not set the session cookie correctly.

To prevent these problems, the best solution is to deactivate the WAC for rendering calls from PhantomJS.

Edit `/etc/hosts` and add the hostname of your ILIAS installation to the localhost addresses
This will keep all requests from phantomjs on the same host.

    127.0.0.1       localhost www.my-ilias-host.de
    ::1             localhost ip6-localhost ip6-loopback www.my-ilias-host.de

Edit `.htaccess` in the ILIAS root directory (or the copied settings in your Apache configuration, if you don't allow overrides).
Add two condition before the rewrite rule for the WAC, so that it is only active for foreighn requests:

    RewriteCond %{REMOTE_ADDR} !=127.0.0.1
    RewriteCond %{REMOTE_ADDR} !=::1
	RewriteRule ^data/.*/.*/.*$ ./Services/WebAccessChecker/wac.php [L]


Debugging of the PDF generation
-------------------------------
If the PDF generation fails for some reason you may want to test it manually on the server to get additional debugging output.

1. Activate the ILIAS log with INFO level for the 'Root' component
2. Generate an archive with the config options 'Keep Directory' and 'Keep Jobfile'
3. Search in the ILIAS log for 'ilTestArchiveCreatorPDF::generateJobs'
4. Copy the whole logged command line
5. Open a shell on your server and change to the root folder of your ILIAS installation
6. Paste the command and run it
7. Look at the debugging output pf PhantomJS

VERSIONS
--------
1.4.0 for ILIAS 7 (2021-12-09)
- compatibility with ILIAS 7

1.3.2 for ILIAS 5.4 (2020-08-05)
- use proxy settings of ILIAS for PDF generation
- added switch to replace http(s) url of images etc. by file urls for PDF generation

1.3.1 for ILIAS 5.4 (2019-10-17)
- compatibility with ILIAS 5.4.6

1.3.0 for ILIAS 5.4 (2019-07-24)
- compatibility with ILIAS 5.4.4 

1.2.1 for ILIAS 5.2 and 5.3 (2019-07-18)
- fixed display of MC/SC questions if styles are not included
- configure archive creation permissions of normal users (having only write access to a test)

1.2.0 for ILIAS 5.2 and 5.3 (2019-02-20)
- provided session cookies for PhantomJS
- included javascript related question styles
- added config option 'Keep Jobfile'
- added config option 'Any SSL Protocol'
- added config option 'Ignore SSL Errors'
- added config option 'Double Rendering'
- added config option 'Minimum Waiting Time (ms)'
- added config option 'Maximum Waiting Time (ms)'
- added setting 'Include Question'
- added setting 'Include Answers'
- added setting 'Questions with Best Solution'
- added setting 'Answers with Best Solution'

1.1.1 for ILIAS 5.2 and 5.3 (2018-05-08)
-  allow to omit the systems styles for PDF generation
   (the web font prevents the PDF generated with PhantomJS from being searchable)

1.1.0 for ILIAS 5.2 and 5.3 (2018-05-07)
- compatibility for ILIAS 5.3
- fixed output of question ids on console when run by cron
- added an index.html to the archive
- included print and pdf styles of the test object

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