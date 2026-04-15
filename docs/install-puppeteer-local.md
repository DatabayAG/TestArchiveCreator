# Install Puppeteer Locally

[Puppeteer](https://pptr.dev) is a javascript library that uses a headless Chrome browser for automated working with web pages. Please look at the Puppeteer web site https://pptr.dev for general information and installation instructions.

Before you install if, please check if there are any known vulnerabilities for the current version, e.g. on https://socket.dev/npm/package/puppeteer

To be directly called for PDF generation, puppeteer must be installed on the server that runs the PHP processes of ILIAS and must be executable by the user of the PHP process.

The following installation procedure has been tested on Ubuntu 20.04 running ILIAS with user `www-data` and group `www-data`. You should do the installation steps as **root user** on the server. You may choose your preferred installation directory - here we use `/srv/puppeteer`.

First install some dependencies globally:
````
apt install nodejs
apt install libxdamage
````

The puppeteer installation will automatically add the headless chrome to the home directory of the current user. To avoid this being done in the home of root, create a temporary user for the installation and change the directory ownership afterwards.

````
useradd -d /srv/puppeteer -m -s /bin/bash puppeteer
su puppeteer
cd /srv/puppeteer
npm install puppeteer --ignore-scripts
npm audit
````
The last command should produce 'found 0 vulnerabilities'. You may also use a tool like Snyk to check for vulnerabilities. If everything looks fine, continue with the installation.

````
cd /node_modules/puppeteer
node install.mjs
exit
userdel puppeteer
chown -R www-data:www-data /srv/puppeteer
````

Now go to the plugin configuration in ILIAS and choose *Puppeteer on ILIAS Server* for the PDF generation. Enter the following paths:

**Node Modules**
````
/srv/puppeteer/node_modules/
````

**Chrome** (your path below `puppeteer/chrome` may have a different version number)
````
/srv/puppeteer/.cache/puppeteer/chrome/linux-146.0.7680.153/chrome-linux64/chrome
````

**Node** (path may be different on other distributions)
````
/usr/bin/node
````

Run the archive creation for a test. If an error occurs or no PDF file is created, look at the ILIAS log for details.



