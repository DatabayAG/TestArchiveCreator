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

var jobs = JSON.parse(fs.read(system.args[1]));
var jobnum = 0;

doJobs();

/*****************************************************************************/

/**
 * Job processing
 */
function doJobs ()
{
	// get the current job or exit if finished
	if (jobnum >= jobs.length) {
		phantom.exit();
	}
	var job = jobs[jobnum];
	var headLeft = job['headLeft'];
    var headRight = job['headRight'];
    var footLeft = job['footLeft'];
	var time = job['time'];
	var orientation = job['orientation'];
	console.log('Job ' + jobnum + ': '+ job['sourceFile']);
	jobnum++;

	// create and render the page
    var page = webpage.create();
	page.zoomFactor = 1;
    page.paperSize = {
        format: 'A4',
        orientation: orientation,
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
	page.open('file:///'+job['sourceFile'], function(status) {
		
		if (status == 'fail') {
			page.content = 'Loading Failed: ' + job['sourceFile'];
			page.render(job['targetFile'],  {format: 'pdf', quality: '100'});
			page.close();
			doJobs();
			return;
		}
		
		waitFor (	
				
			// Condition
			function() { 
				return page.evaluate(function() {
					// this boolean variable must be set by the page
					return true;
				});
			}, 	
			
			// Action
			function() {				
				page.render(job['targetFile'],  {format: 'pdf', quality: '100'});
				page.close();
				doJobs();
			},	
			
			// Timeout 10s
			10000		
		); 
    });		
}


/**
 * Wait until the test condition is true or a timeout occurs. Useful for waiting
 * on a server response or for a ui change (fadeIn, etc.) to occur.
 *
 * @param testFx callback function that evaluates to a boolean
 * @param onReady callback function that is called after fulfilled condition or timeout 
 * @param timeOutMillis the max amount of time to wait. If not specified, 3 sec is used.
 * 
 * @see https://github.com/ariya/phantomjs/blob/master/examples/waitfor.js
 */
function waitFor(testFx, onReady, timeOutMillis) 
{
    var maxtimeOutMillis = timeOutMillis ? timeOutMillis : 3000; 
    var start = new Date().getTime();
    var condition = false;
	
    var interval = window.setInterval(function() 
		{
            if ( (new Date().getTime() - start < maxtimeOutMillis) && !condition ) {
                // Not time-out yet and condition not yet fulfilled
                condition = testFx(); 
            } 
			else {
                if(condition) {
					// Condition fulfilled 
                    console.log("'waitFor()' finished in " + (new Date().getTime() - start) + "ms.");
                } else {
                    // Condition still not fulfilled
                    console.log("'waitFor()' timeout");
                }
				window.clearInterval(interval); 
				onReady();
            }
		}, 250); // repeat check every 250ms
}
