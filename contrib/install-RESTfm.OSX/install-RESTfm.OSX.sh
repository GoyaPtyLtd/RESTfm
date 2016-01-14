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
ARGS=("$@")
LOGNAME=`logname`

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

# A general use one second granular timestamp.
TIMESTAMP=`date +%Y%m%d%H%M%S`

### Output functions ###

showHeader () {
    cat <<EOCAT

+-----------------------------------------------------------------------------+
|                       RESTfm installer for Max OS X                         |
+-----------------------------------------------------------------------------+

EOCAT
}

setupLogfile () {
    # Log all output to file.
    local LOGFILE="${BASEDIR}/${ARGV0}.${TIMESTAMP}.log"
    # DEBUG
    LOGFILE="foo.log"
    # Truncate log file, and redirect STDOUT and STDERR through tee.
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

##
# Check that we are root.
#
check_Privilege() {
    local MSGPREFIX='Check administrator privilege:'

    if [ "$EUID" != "0" ]; then
        log_failure_msg "$MSGPREFIX No - ${LOGNAME}"
        echo 'Error: Please try again using: sudo' "$0" "${args[@]}"
        exit 1
    fi

    log_success_msg "$MSGPREFIX Yes (${LOGNAME})"
}

##
# Check the user answers Y or y, else abort.
#
check_Y() {
    echo "$1"
    local CONTINUE
    read CONTINUE
    if [ "X${CONTINUE}" != 'XY' -a "X${CONTINUE}" != 'Xy' ]; then
        echo "++ user aborted with \"${CONTINUE}\", exiting."
        exit
    fi
}

##
# Check the RESTfm report page for any errors.
check_RESTfmReport() {
    MSGPREFIX='Check RESTfm report:'
    curl -s -L http://localhost/RESTfm/report.php | grep -q "RESTfm is working"
    local RET=$?

    if [ "$RET" != "0" ]; then
        log_failure_msg "${MSGPREFIX} not working"
        echo
        echo "Please check: http://localhost/RESTfm/report.php"
        echo
        exit 1
    fi

    log_success_msg "${MSGPREFIX} http://localhost/RESTfm is working"
}

### Utility Functions ###

##
# Install the appropriate Apache config in RESTfm/contrib
#
installRESTfmApacheConfig() {
    local MSGPREFIX="Install RESTfm Apache config:"
    local SRC="${BASEDIR}/httpd-RESTfm.FMS13.Apache24.OSX.conf"
    local DST="/Library/FileMaker Server/HTTPServer/conf/extra/"

    cp "${SRC}" "${DST}"
    local RET=$?

    if [ "$RET" != "0" ]; then
        log_failure_msg "${MSGPREFIX} error copying file"
        exit 1
    fi

    log_success_msg "${MSGPREFIX} success"
}

##
# Update the FMS Apache httpd.conf file to include the RESTfm .conf
#
updateFMSApacheConfig() {
    local MSGPREFIX="Update FMS Apache config:"
    local CONFBASENAME="/Library/FileMaker Server/HTTPServer/conf"
    local SRC="${CONFBASENAME}/httpd.conf"
    local DST="${CONFBASENAME}/httpd.conf.${TIMESTAMP}.bak"

    cp "${SRC}" "${DST}"
    local RET=$?

    if [ "$RET" != "0" ]; then
        log_failure_msg "${MSGPREFIX} error backing up original file"
        exit 1
    fi

    cat << EOFMSAPACHEUPDATE >> "${SRC}"

# RESTfm - ${TIMESTAMP} - ${ARGV0} - ${LOGNAME}
Include conf/extra/httpd-RESTfm.FMS13.Apache24.OSX.conf
EOFMSAPACHEUPDATE

    log_success_msg "${MSGPREFIX} success"
}

##
# Restart FMS Apache web server instance.
#
restartFMSApache() {
    log_daemon_msg 'Restarting Web Server'
    launchctl start com.filemaker.httpd.restart

    # We want to give httpd some time to settle otherwise any web queries
    # following this function might fail.
    local COUNT=3
    while [ $COUNT -gt 0 ]; do
        log_progress_msg $COUNT
        sleep 1
        COUNT=$(($COUNT - 1))
    done
    log_end_msg 0
}

### Main ###

echo

setupLogfile

showHeader

date

check_OSXVersion

check_FMSVersion

check_Privilege

check_Y "Type Y to continue with installation, anything else will abort."

installRESTfmApacheConfig

updateFMSApacheConfig

restartFMSApache

check_RESTfmReport

echo "Done."
echo

exit
