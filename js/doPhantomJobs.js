/**
 * Control script for PhantomJS
 */

var system  = require('system');
var fs      = require('fs');
var webpage = require('webpage');

if (system.args.length !== 2) {
    console.log('Usage: phantomjs doPhantomJobs.js <jobfile.json>');
    phantom.exit(1);
}

var config = JSON.parse(fs.read(system.args[1]));
var jobnum = 0;

setCookies();
doJobs();

/*****************************************************************************/

/**
 * Job processing
 */
function doJobs ()
{
	// get the current job or exit if finished
	if (jobnum >= config.jobs.length) {
        phantom.exit();
    }

	var job = config.jobs[jobnum];
    var minRenderingWait = config.minRenderingWait;
    var maxRenderingWait = config.maxRenderingWait;
	var headLeft = job.headLeft;
    var headRight = job.headRight;
    var footLeft = job.footLeft;

	var time = job.time;

	console.log('Job ' + jobnum + ': '+ job.sourceFile);
	jobnum++;

	// create and render the page
    var page = webpage.create();
	page.zoomFactor = 1;
    page.paperSize = {
        format: 'A4',
        orientation: config.orientation,
        margin: '1cm',
        header: {
            height: "1cm",
            contents: phantom.callback(function(pageNum, numPages) {
                if (pageNum > 1) {
                    return '<span style="font-size: 8pt; font-family:sans-serif; float:left">' + headLeft + '</span>' +
                    '<span style="font-size: 8pt; font-family:sans-serif; float:right">' + headRight + '</span>';
                }
            })
        },
        footer: {
            height: "1cm",
            contents: phantom.callback(function(pageNum, numPages) {
                return '<span style="font-size: 8pt; font-family:sans-serif; float:left">' + footLeft + '</span>' +
				'<span style="font-size: 8pt; font-family:sans-serif; float:right">' + pageNum + ' / ' + numPages + '</span>';
            })
        }
    };
	page.open('file:///'+job.sourceFile, function(status) {
		
		if (status === 'fail') {
			page.content = 'Loading Failed: ' + job.sourceFile;
			page.render(job.targetFile,  {format: 'pdf', quality: '100'});
			page.close();
			doJobs();
			return;
		}
		
		waitFor (	
				
			// Condition
			function() { 
				return page.evaluate(function() {
					// ensure that all images are loaded
					return document.readyState === 'complete';
				});
			}, 	
			
			// Action
			function() {
                console.log('render ' + job.targetFile);
                page.render(job.targetFile,  {format: 'pdf', quality: '100'});
				page.close();
				doJobs();
			},

            // check interval and minimum waiting time (milliseconds)
            minRenderingWait,

			// Timeout (milliseconds)
            maxRenderingWait
		); 
    });		
}


/**
 * Wait until the test condition is true or a timeout occurs. Useful for waiting
 * on a server response or for a ui change (fadeIn, etc.) to occur.
 *
 * @param testFx callback function that evaluates to a boolean
 * @param onReady callback function that is called after fulfilled condition or timeout 
 * @param minMs check interval and minimum milliseconds to wait
 * @param maxMs maximum milliseconds to wait
 * 
 * @see https://github.com/ariya/phantomjs/blob/master/examples/waitfor.js
 */
function waitFor(testFx, onReady, minMs, maxMs)
{
    minMs = minMs ? minMs : 1;
    maxMs = maxMs ? maxMs : 1;

    var start = new Date().getTime();

    var interval = window.setInterval(function() 
		{
		    var current = new Date().getTime();

            // timeout or condition is true
		    if ((current - start >= minMs) &&
                (current - start >= maxMs) || testFx()) {

		        // Condition fulfilled
                console.log("Waiting finished in " + (current - start) + "ms.");
                window.clearInterval(interval);
                onReady();
            }

		}, minMs); // repeat check every minimum milliseconds
}


/**
 * Set the coolies needed for loading images if Web Access Checker is on
 */
function setCookies()
{
    phantom.addCookie ({
        'name'     : 'ilClientId',
        'value'    : config.clientId,
        'domain'   : config.cookieDomain,
        'path'     : config.cookiePath,
        'httponly' : config.cookieHttpOnly,
        'secure'   : config.cookieSecure,
        'expires'  : config.cookieExpires
    });

    phantom.addCookie ({
        'name'     : 'PHPSESSID',
        'value'    : config.sessionId,
        'domain'   : config.cookieDomain,
        'path'     : config.cookiePath,
        'httponly' : config.cookieHttpOnly,
        'secure'   : config.cookieSecure,
        'expires'  : config.cookieExpires
    });
}
