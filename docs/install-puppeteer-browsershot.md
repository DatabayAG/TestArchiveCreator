# Install Puppeteer for use with Browserhot

Look at the Puppeteer web site https://pptr.dev for general installation instructions. 

Our test installation on Ubuntu 20.04 followed the instructions given by browsershot (https://spatie.be/docs/browsershot/v2/requirements),
especially 'Installing puppeteer a Forge provisioned server' with a few changes:

* Created a home directory `/home/www-data` for the user of the web server and  ran the installation there
* Didn't use the `--location=global` switch for the puppeteer installation
* Changed the ownwerships of all files under `/home/www-data` to www-data
* Entered the following paths in the plugin configuration (may be different for other distributions and futore versions of puppeteer):
  * Node Modules: `/home/www-data/node_modules/`
  * Chrome: `/home/www-data/.cache/puppeteer/chrome/linux-1108766/chrome-linux/chrome`
  * Node: `/usr/bin/node`
  * Npm: `/usr/bin/npm`




