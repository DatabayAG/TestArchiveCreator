# Install PhantomJS for the TestArchiveCreator

NOTE: the devlopment of Phantomjs is discontinued and support for it will be removed in the future.

Look at the PhantomJS web site http://phantomjs.org for general installation instructions. 

Short hint for Debian based systems (thanks to Rachid Rabah):
* `apt-get install phantomjs` 
* PhantomJS will be located in `/user/bin/phantomjs`
* Set the following environment variables:
  * `export QT_QPA_PLATFORM=offscreen`
  * `export QT_QPA_FONTDIR=/usr/share/fonts/truetype`
* If you need a font with Japanese characters:
  `apt-get install fonts-takao`

You may also take a binary distribution fom https://bitbucket.org/ariya/phantomjs/downloads/

* PhantomJS 2.1.1 is the preferred one, but renders web fons as graphis which results in large, unsearchable PDFs.
  You may prevent this by deactivating 'Use System Styles' in the plugin configuration, but this leads to ugly output.

* PhantomJS 1.9.8 is able to render web fonts, so 'Use System Styles' can be activated. But this older version seems to have
  some problems with ssl and images, so you should set 'Any SSL Protocoll', 'Ignore SSL Errors', and 'Render Twice" in the
  plugin configuration.
