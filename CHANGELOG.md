# Change Log

Plugin versions for different ILIAS releases are provided in separate branches of this repository.

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
