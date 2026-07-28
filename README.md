# TestArchiveCreator

Copyright (c) 2017-2023 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

**Further maintenance can be offered by [Databay AG](https://www.databay.de).**

Versions: see [Changelog](CHANGELOG.md). Plugin versions for different ILIAS releases are provided in separate branches of this repository.

This plugin for the LMS ILIAS open source allows the creation of zipped archives with PDF files for written tests.

The actual PDF rendering is done by a headless browser which has to be installed in the web server. Corrently the plugin supports two renderers:

* Puppeteer on the ILIAS server, see [Installation](./docs/install-puppeteer-local.md)
* Puppeteer on a separate Server, see this [Server Script](https://github.com/DatabayAG/tarc-pdf)

## Issues

Please use the official ILIAS bug tracker "Mantis" for bug reporting: https://mantis.ilias.de
* Select "ILIAS Plugins" as Project
* Filter by Category "TestArchiveCreator"

## Plugin installation

1. Put the content of the plugin directory in a subdirectory under your ILIAS main directory:
`public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/TestArchiveCreator`
2. Move to the base directory of your ILIAS and run `composer du` to reload the current plugin version
3. Open Administration > Extending ILIAS > Plugins
4. Install and activate the plugin
5. Open the plugin configuration
6. Edit the plugin configuration and enter at least the server paths of the chosen renderer.


## Usage

1. Mover to the tab "Export" in the test.
3. Click "Settings" in the toolbar to change some properties of the archive creation.
2. Click the button "Create" in the toolbar to create a zipped archive.

The archive containes separate PDF files for the questions in the test and the test runs of participants. Overviews are written as csv html files.

## Planned Creation

Archive creation may take a long time for large tests. For this reason the plugin allows a planned creation of the archive in each test. This requires some setup steps.

Set up a call of the ILIAS cron jobs on your web server, see the ILIAS installation guide:
https://www.ilias.de/docu/goto_docu_pg_8240_367.html

Set a HTTP path in the file `ilias.ini.php`.This path is needed to load images for the archive and it can't
be automatically determined in the context of a cron job. It must point to the public directory of ilias without a slash at the end.

````
[server]
http_path = "https://ilias.your.domain/public"
````

Configure the cron job:

1. Open Administration / System Settings and Maintenance / Cron Jobs 
2. Activate the 'Test Archive Creation' job
3. Set a reasonable schedule for the job, e.h. daily.

Now you can set a time in the settings of the archive creation. When the cron job is called and the time is due, it
will create the archive.


## Debugging of the PDF generation
If the PDF generation fails for some reason you may want to test it manually on the server to get additional debugging output.

1. Activate the ILIAS log with INFO level for the 'Root' component
2. Generate an archive with the config options 'Keep Directory' and 'Keep Jobfile'
3. Search in the ILIAS log for 'ilTestArchiveCreatorPDF::generateJobs'
4. Copy the whole logged command line
5. Open a shell on your server and move to the root folder of your ILIAS installation
6. Paste the command and run it
7. Look at the debugging output

## Directory Cleanup
With version 11.0.2 the archive generation is done in a subdirectory of the ILIAS temporary directory. The name of the working directory is random to avoid conflicts between two creation processes for the same test. This directory will be deleted after 10 days if the ILIAS cron job 'Clean Temp Directory' is active, even if "Keep Directory" is set in the plugin administration.