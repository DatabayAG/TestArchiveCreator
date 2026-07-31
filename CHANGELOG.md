# Change Log

Plugin versions for different ILIAS releases are provided in separate branches of this repository.

## 9.3.10 for ILIAS 9 (2026-07-31)
- Support more character transliterations in filename sanitation

## 9.3.9 for ILIAS 9 (2026-07-29)
- Fix error for missing 'archive_export' directory

## 9.3.8 for ILIAS 9 (2026-07-28)
- Fix #46441: Use random temporary directory to avoid process conflicts

## 9.3.7 for ILIAS 9 (2026-03-02)
- Fix: Deactivate for anonymous tests

## 9.3.6 for ILIAS 9 (2025-11-11)
- Fix: ilCtrl issue with cron job

## 9.3.5 for ILIAS 9 (2025-05-21)
- Fix: Archive created without .zip suffix

## 9.3.4 for ILIAS 9 (2025-05-07)
- Fix: Export of SingleChoice Question with Feedback does not work
  https://mantis.ilias.de/view.php?id=44857
  This could only be fixed by deactiating the inline feedback for the output

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
