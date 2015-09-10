#!/bin/bash

# RESTfm install script for use on Mac OS X where FileMaker Server has
# been deployed on the same machine.
#
# Supported Operating System versions:
#   Mac OS X 10.10 Yosemite
#   Mac OS X 10.9 Mavericks
#
# Supported software versions:
#   Filemaker Server 14
#   Filemaker Server 13
#
# Copyright (C) 2015, Goya Pty. Ltd.
# All rights reserved.

# Identify precisely where we are.
ARGV0=`basename $0`
cd `dirname $0`
BASEDIR=`pwd`

### Global variables ###

### Script begins ###

#
# script usage.
#
usage() {
    echo ""
    echo "Usage: ${ARGV0} [-h|--help]"
    echo "  -h          This help."
    echo ""
}

#if [ $# -le 0 ]; then
#    usage
#    exit 1
#fi

#
# Parse command line arguments.
#
while [ $# -ge 1 ]; do
    case "$1" in
    -h|--help)
        usage
        exit
        ;;
    *)
        usage
        exit 2
        ;;
    esac
    shift
done

# Define LSB log_* functions.
. "${BASEDIR}"/init-functions

### Output functions ###

showHeader () {
    cat <<EOCAT

+-----------------------------------------------------------------------------+
|                       RESTfm installer for Max OS X                         |
+-----------------------------------------------------------------------------+

EOCAT
}

setupLogfile () {
    local TIMESTAMP=`date +%Y%m%d%H%M%S`

    # Log all output to file.
    local LOGFILE="${BASEDIR}/${ARGV0}.${TIMESTAMP}.log"
    # DEBUG
    LOGFILE="foo.log"
    > "$LOGFILE"
    exec >  >(tee -ia "$LOGFILE")
    exec 2> >(tee -ia "$LOGFILE" >&2)

    echo "All output is logged to: \"${LOGFILE}\""
}

### Check-or-die functions ###

check_OSX () {
    local MSG='Check for Mac OS X'
    log_success_msg $MSG
}

##
# Check:
#   - that we are running on Mac OS X.
#   - that we are on a supported version of Mac OS X.
#   - that we are not running on Mac OS X Server Edition.
check_OSXVersion() {
    local MSGPREFIX='Check Mac OS X version: '

    # Ensure we have sw_vers, and we are Mac OS X.
    local PRODUCTNAME=''
    [ -x "`which sw_vers`" ] && PRODUCTNAME=`sw_vers -productName`
    if [ "$PRODUCTNAME" != 'Mac OS X' ]; then
        log_failure_msg "$MSGPREFIX Mac OS X not detected"
        exit 1
    fi

    # Ensure we are on a supported version of Mac OS X.
    local OSXVERSION=`sw_vers -productVersion`
    # Trim off any patch version, e.g. 10.10.1 -> 10.10
    OSXVERSION=`echo "${OSXVERSION}" | grep -Eo '^[0-9]+\.[0-9]+'`
    case "$OSXVERSION" in
        "10.10")
            log_success_msg "$MSGPREFIX $OSXVERSION Yosemite"
            ;;
        "10.9")
            log_success_msg "$MSGPREFIX $OSXVERSION Mavericks"
            ;;
        *)
            log_failure_msg "$MSGPREFIX $OSXVERSION, unsupported"
            exit 1
            ;;
    esac

    # Ensure we are not running on Mac OS X Server Edition.
    if [ -e '/System/Library/CoreServices/ServerVersion.plist' ]; then
        log_failure_msg 'Mac OS X Server Edition detected. This is not supported'
        exit 1
    fi
}

##
# Check:
#   - that FMS appears to be installed.
#   - that FMS is a supported version.
#   - that FMS has both WPE and PHP enabled.
check_FMSVersion() {
    local MSGPREFIX='Check FileMaker Server version: '

    local FMSADMINBIN='/Library/FileMaker Server/Database Server/bin/fmsadmin'
    local FMSVERSIONJAR='/Library/FileMaker Server/Admin/admin-server/WEB-INF/lib/fmversion.jar'
    local FMSSERVERCONFIG='/Library/FileMaker Server/Admin/conf/server_config.xml'

    # Ensure that FMS is installed on this system.
    if [ ! -r "$FMSADMINBIN" ]; then
        log_failure_msg "$MSGPREFIX FileMaker Server not detected"
        exit 1
    fi

    # Ensure FMS is a supported version.
    local FMSVERSION=`unzip -c "$FMSVERSIONJAR" META-INF/MANIFEST.MF | grep Specification-Version: | grep -Eo '[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+'`
    local FMSMAJORVERSION=`echo "$FMSVERSION" | grep -Eo '^[0-9]+'`
    case "$FMSMAJORVERSION" in
        '14'|'13')
            log_success_msg "$MSGPREFIX $FMSVERSION"
            ;;
        *)
            log_failure_msg "$MSGPREFIX $FMSVERSION, unsupported"
            exit 1
            ;;
    esac

    # Ensure that both WPE and PHP are enabled.
    local FMSWPEPHP=''
    FMSWPEPHP=`xpath "$FMSSERVERCONFIG" '//component[@name="WPE"]//technology[@name="PHP"]//parameter[@name="enabled"]/text()' 2>/dev/null`
    if [ "X${FMSWPEPHP}" != 'Xtrue' ]; then
        log_failure_msg "FileMaker Server WPE and PHP not enabled"
        exit 1
    fi

}

### Utility Functions ###

### Main ###

setupLogfile

showHeader

date

check_OSXVersion

check_FMSVersion

# Note: Checking if WPE+PHP is enabled in FMS:
# xpath "/Library/FileMaker Server/Admin/conf/server_config.xml" '//component[@name="WPE"]//technology[@name="PHP"]//parameter[@name="enabled"]/text()'
# Found 1 nodes:
# -- NODE --
# true


# Note: Checking FMS version:
# unzip -c /Library/FileMaker\ Server/Admin/admin-server/WEB-INF/lib/fmversion.jar META-INF/MANIFEST.MF | grep Specification-Version:
# Specification-Version: 14.0.2.226


exit

log_daemon_msg "Doing something cool"
log_progress_msg 'progress .'
sleep 1
log_progress_msg '.'
sleep 1
log_progress_msg '.'
sleep 1
log_end_msg 0

log_success_msg 'Standalone success message'
log_warning_msg 'Standalone warning message'
log_failure_msg 'Standalone failure message'

log_daemon_msg 'test daemon message - 0'
log_success_msg 'Test success message'
log_end_msg 0

log_daemon_msg 'test daemon message - 2'
echo -n "Sending a two!"
log_end_msg 2

log_daemon_msg 'test daemon message - 1'
log_failure_msg 'Test failure message'
log_end_msg 1
