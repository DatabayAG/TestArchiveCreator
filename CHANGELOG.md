# Change Log

## 1.6.1 for ILIAS 8 (2024-03-24)
- fixed 0040794: Description regarding sample solutions is not precise
- fixed 0040728: Text Subset Question with problematic display of best solution in html
- Improved MathJax handling. MathJax settings for server-side rendering are respected separately: 
  - 'Use for HTML Export' for the HTML files in the archive 
  - 'Use for PDF Generation' for the optional PDF files. 
  - 'Use for Browser' must be activated if TeX in STACK questions should be rendered server-side. 
  - If server-side rendering is not enabled, then the Script URL client-side rendering is added to the HTML files in the archive.
- uploaded files are added as assets to the archive and linked on the participant page
- files of the page editor file list are added as assets to the archive

## 1.6.0 for ILIAS 8 (2024-03-05)
- added config option to include local assets (Media, CSS and JS File) in the archive
- added delivery script for assets in pdf creation process (bypassing WAC)
- removed config option to use file urls for PDF generation with phantomjs
- made pdf creation optional
- new pdf creation mode using an external server with puppeteer (experimental)
- added config option to include the test log
- added config option to include an examination protocol
- refactored code for ILIAS 8 (file handling, function types, template usage)

## 1.5.1 for ILIAS 8 (2023-05-11)
- works with the cron job plugin
- initial support for browsershot

## 1.5.0 for ILIAS 8 (2023-04-25)
- first version for ILIAS 8
- not yet extensively tested
- Workaround for deleted ilUtil filesystem functions, needs refactoring

## 1.4.2 for ILIAS 7 (2023-01-16)
- corrected some typos anf gendered (thx to Mirco Hilbert)
- removed adding of ta.css (causes error since ILIAS 7.8)

## 1.4.1 for ILIAS 7 (2021-12-09)
- compatibility with ILIAS 7
- fixed redirection after manual cron job execution

## 1.3.2 for ILIAS 5.4 (2020-08-05)
- use proxy settings of ILIAS for PDF generation
- added switch to replace http(s) url of images etc. by file urls for PDF generation

## 1.3.1 for ILIAS 5.4 (2019-10-17)
- compatibility with ILIAS 5.4.6

## 1.3.0 for ILIAS 5.4 (2019-07-24)
- compatibility with ILIAS 5.4.4

## 1.2.1 for ILIAS 5.2 and 5.3 (2019-07-18)
- fixed display of MC/SC questions if styles are not included
- configure archive creation permissions of normal users (having only write access to a test)

## 1.2.0 for ILIAS 5.2 and 5.3 (2019-02-20)
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

## 1.1.1 for ILIAS 5.2 and 5.3 (2018-05-08)
-  allow to omit the systems styles for PDF generation
   (the web font prevents the PDF generated with PhantomJS from being searchable)

## 1.1.0 for ILIAS 5.2 and 5.3 (2018-05-07)
- compatibility for ILIAS 5.3
- fixed output of question ids on console when run by cron
- added an index.html to the archive
- included print and pdf styles of the test object

## 1.0.3 for ILIAS 5.2 (2018-02-08)
- new config setting to keep the creation directory after zipping
- logging of the phantomjs command line with INFO level

## 1.0.2 for ILIAS 5.2 (2018-01-31)
- logging of phantomjs calls
- jobfile content is logged with DEBUG level
- phantomjs console message is logged with INFO level
- not executable phantomjs or exceptions are logged with WARNING level

## 1.0.1 for ILIAS 5.2 (2018-01-18)
- cron job support