# Change Log

Plugin versions for different ILIAS releases are provided in separate branches of this repository.

## 11.0.3 for ILIAS 1 (2026-07-31)
- Support more character transliterations in filename sanitation

## 11.0.2 for ILIAS 11 (2026-07-02)
- Fix 0046441: Use random temporary directory to avoid process conflicts

## 11.0.1 for ILIAS 11 (2026-07-16)
- Fix 0048031: TAC Export with all question types throws error

## 11.0.0 for ILIAS 11 (2026-05-22)
- compatibility with ILIAS 11, PHP 8.3 and 8.4
- refactor db update steps
- allow installation via setup
- change config and settings forms to the UI component
- integrate cron job (separate plugin is obsolete)
- export of mathjax to the archive

## 10.0.3 for ILIAS 10 (2026-03-02)
- Fix: Deactivate for anonymous tests

## 10.0.2 for ILIAS 10 (2026-01-08)
- Fix: Remove ilYuiUtil initialisations

## 10.0.1 for ILIAS 10 (2025-11-11)
- Fix: ilCtrl issue with cron job

## 10.0.0 for ILIAS 10 (2025-11-05)
- compatibility with ILIAS 10, PHP 8.2 and 8.3

## 9.3.3 for ILIAS 9 (2025-03-03)
- Fix: export results for scored passes only (like in results export on export tab)

## 9.3.2 for ILIAS 9 (2025-02-25)
- Fix: getting the processing time from a time limited test

## 9.3.1 for ILIAS 9 (2025-01-27)
- Fix: allow archive creation for tests with deleted user accounts

## 9.3.0 for ILIAS 9 (2025-01-06)
- Feature: optionally add test result files to the archive

## 9.2.0 for ILIAS 9 (2024-12-16)
- Fix: replace browsershot with direct call of puppeteer

## 9.1.0 for ILIAS 9 (2024-12-09)
- Feature: add an HTML file with server info to the archive
- Fix: avoid dependency conflicts with SOAP authentication

## 9.0.1 for ILIAS 9 (2024-12-04)
- Update GitHub address for the PDF server
- Add fault tolerance for failed PDF creation

## 9.0.0 for ILIAS 9 (2024-11-07)
- compatibility with ILIAS 9.5 and higher, PHP 8.1 and 8.2 
- removed phantomjs and its settings for PDF generation
- renamed PDF generation option "Browsershot" to "Puppeteer on ILIAS Server"
- updated installation instruction for puppeteer
- applied ILIAS coding style
- new plugin version numbering: ILIAS version . new features version . bugfix version

## 1.6.2 for ILIAS 8.11+ (2024-06-25)
- fix cron job failure due to changed ilCtrl interface since ILIAS 8.11
- update the link to the cron job plugin repository

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
