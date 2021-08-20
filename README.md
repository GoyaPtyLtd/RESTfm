# RESTfm
RESTful Web Services for FileMaker Server

RESTfm turns a FileMaker Server into a RESTful Web Service, so you can
access your FileMaker Server databases via HTTP(S) using a common REST
architecture with easy to understand API calls.

**Website:**
https://restfm.com

**RESTfm Installation and Programming Interface Manual:**
https://docs.restfm.com

RESTfm is Copyright (c) 2011-2021 Goya Pty Ltd, and is licensed under The
MIT License. For full copyright and license information, please see the LICENSE
file distributed with this package.

-----------------------------------

### Download
Officially packaged releases are the quickest way to start, are available in tar and zip formats, and may be downloaded from the "releases" link at the top of the github repository:
https://github.com/GoyaPtyLtd/RESTfm/releases

*Note:* The officially packaged releases are called `RESTfm-{version}.zip` and `RESTfm-{version}.tgz`.
(Please don't be confused by the links to "Source code" that GitHub always includes. "Source code" has not gone through the "build" process, and requires additional configuration to get working.)

### Installation
The installation process is described in the online RESTfm manual:
https://docs.restfm.com/category/5-install

### Support
Product support is available via paid sponsorship:
https://restfm.com/support

----------------------------------------

## Using the master branch of RESTfm from GitHub.
The master branch hosted on GitHub is considered stable, and packaged releases are built from this branch at intervals.
With a little extra configuration, it is possible to run RESTfm directly from a git repository clone.

### Installation
#### Prerequisites
  * A webserver (Apache >= 2.2, or IIS >= 7.0) with write access to the document directory.
  * FileMaker Server 11 or above is required, but does not need to reside on the same machine as RESTfm.
  * PHP version 7.0 or above.
  * If running Apache, configure with `AllowOverride All` for the RESTfm directory so that `.htaccess` functions correctly.

#### Setup example suitable for OS X/Linux
    cd /<your web doc dir>
    git clone https://github.com/GoyaPtyLtd/RESTfm.git
    cd RESTfm
    cp RESTfm.ini.dist RESTfm.ini
    cp .htaccess.dist .htaccess
    cp -a FileMaker.dist FileMaker
  * When setting up on IIS, also ensure `web.config.dist` is copied to `web.config`
  * Use a browser to see if RESTfm needs further configuration: http://example.com/RESTfm/report.php
  * Refer to the RESTfm manual for further configuration information: https://docs.restfm.com

----------------------------------------

### Bugs
Bug reports are welcome via the GitHub issue tracker:
https://github.com/GoyaPtyLtd/RESTfm/issues
