# RESTfm
RESTful Web Services for FileMaker Server

RESTfm turns your FileMaker Server into a RESTful Web Service, so you can
access your FileMaker Server databases via HTTP using a common REST
architecture with easy to understand API calls.

**Website:**
http://restfm.com/

**RESTfm Installation and Programming Interface Manual:**
http://restfm.com/manual

RESTfm is Copyright (c) 2011-2015 Goya Pty Ltd, and is licensed under The
MIT License. For full copyright and license information, please see the LICENSE
file distributed with this package.

-----------------------------------

## Using RESTfm on production systems.
The master branch code hosted on github is under development, and may contain bugs. It is not recommended for use on production systems.

### Download
Packaged releases should be used on production systems. These are available in tar and zip formats and may be downloaded from http://restfm.com/demo and also under the "releases" link at the top of the github repository:  
https://github.com/GoyaPtyLtd/RESTfm/releases

### Support
Product support is available via paid sponsorship:  
http://restfm.com/help

----------------------------------------

## Using the development version of RESTfm from github.
RESTfm developers and those interested in testing the latest features may run RESTfm directly from a local git repository.

### Installation
#### Prerequisites 
  * A webserver with write access to the document directory.
  * FileMaker Server is required, but does not need to reside on the same machine as RESTfm.
  * PHP version 5.3 or above.
  * Apache must be configured with `AllowOverride All` for the RESTfm directory so that `.htaccess` functions.

#### Setup example suitable for Linux/OS X
    cd /<your web doc dir>
    git clone https://github.com/GoyaPtyLtd/RESTfm.git
    cd RESTfm
    cp RESTfm.ini.php.dist RESTfm.ini.php
    cp .htaccess.dist .htaccess
  * Modify `RESTfm.ini.php` and `.htaccess` as necessary for your environment.
  * Use a browser to see if RESTfm needs further configuration: http://example.com/RESTfm
  
### Bugs
The development git code may be buggy, and is unsupported. Bug reports are welcome via the github issue tracker:  
https://github.com/GoyaPtyLtd/RESTfm/issues
