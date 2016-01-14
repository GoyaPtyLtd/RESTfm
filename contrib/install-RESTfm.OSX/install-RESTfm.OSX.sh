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

# Cross platform path resolving function. Original verion by user "arifsaha":
# http://www.linuxquestions.org/questions/programming-9/bash-script-return-full-path-and-filename-680368/page3.html
function abspath {
    if [[ -d "$1" ]]; then
        pushd "$1" >/dev/null
        pwd
        popd >/dev/null
    elif [[ -e $1 ]]; then
        pushd $(dirname $1) >/dev/null
        echo $(pwd)/$(basename $1)
        popd >/dev/null
    else
        echo $1 does not exist! >&2
        return 127
    fi
}

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
# Check that we are in the correct location. i.e. in an immediate subdirectory
# to /Library/FileMaker Server/HTTPServer/htdocs
#
# Initialises global variables:
#   RESTFMPATHMD5   - Unique hash of RESTfm installation directory.
#   RESTFMDIRNAME   - Dirname part of RESTfm installation directory.
#   RESTFMBASENAME  - Basename part of RESTfm installation directory.
#
check_Location() {
    local MSGPREFIX='Check RESTfm location:'
    local REQUIREDHTDOCSPATH='/Library/FileMaker Server/HTTPServer/htdocs'

    # Search back up directory tree from $BASEDIR looking for RESTfm.php,
    # then we know our RESTfm root directory.
    local TESTROOT=$BASEDIR
    local BAILOUT=10
    local RESTFMROOT=''
    while [ $BAILOUT -gt 0 ]; do
        TESTROOT="${TESTROOT}/.."
        if [ -r "${TESTROOT}/RESTfm.php" ]; then
            RESTFMROOT=$TESTROOT
            break
        fi
        BAILOUT=$(($BAILOUT - 1))
    done
    if [ -z "$RESTFMROOT" ]; then
        log_failure_msg "$MSGPREFIX Unable to locate RESTfm.php"
        exit 1
    fi
    RESTFMROOT=$(abspath "$RESTFMROOT")
    RESTFMPATHMD5=`echo -n "${RESTFMROOT}" | md5`
    RESTFMDIRNAME=`dirname "$RESTFMROOT"`
    RESTFMBASENAME=`basename "$RESTFMROOT"`

    # Ensure that the $RESTFMDIRNAME is $REQUIREDHTDOCSPATH
    if [ "$RESTFMDIRNAME" != "$REQUIREDHTDOCSPATH" ]; then
        log_failure_msg "$MSGPREFIX not under FileMaker Server htdocs"
        echo
        echo "Please copy RESTfm folder to: \"$REQUIREDHTDOCSPATH\""
        echo "Then re-run installer from that location."
        echo
        exit 1
    fi

    log_success_msg $MSGPREFIX $RESTFMBASENAME
}

##
# Check that we are root.
#
check_Privilege() {
    local MSGPREFIX='Check administrator privilege:'

    if [ "$EUID" != "0" ]; then
        log_failure_msg "$MSGPREFIX No - ${LOGNAME}"
        echo
        echo 'Please try again using: sudo' "$0" "${args[@]}"
        echo
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
    local SRC="${BASEDIR}/../httpd-RESTfm.FMS13.Apache24.OSX.conf"
    local DST="/Library/FileMaker Server/HTTPServer/conf/extra"
    local DSTFILENAME="${DST}/httpd-RESTfm.${RESTFMPATHMD5}.conf"

    # Clobbering any existing conf for this RESTfm fully qualified pathname.
    echo "# ${TIMESTAMP} - ${ARGV0} - ${LOGNAME}" > "$DSTFILENAME"
    local RET=$?

    if [ "$RET" != "0" ]; then
        log_failure_msg "${MSGPREFIX} error intialising file"
        exit 1
    fi

    # Use sed to update paths in config.
    sed "s|/Library/FileMaker Server/HTTPServer/htdocs/RESTfm|${RESTFMDIRNAME}/${RESTFMBASENAME}|" "$SRC" | \
    sed "s|/Library/FileMaker Server/HTTPServer/htdocs/httpsRoot/RESTfm|${RESTFMDIRNAME}/httpsRoot/${RESTFMBASENAME}|" >> \
    "$DSTFILENAME"
    local RET=$?

    if [ "$RET" != "0" ]; then
        log_failure_msg "${MSGPREFIX} error updating paths"
        exit 1
    fi

    log_success_msg "${MSGPREFIX} success"
}

##
# Update the FileMaker Server htdocs/httpsRoot/ symlink.
#
updateHttpsRootSymlink(){
    local MSGPREFIX="Update httpsRoot symlink:"
    local SRC="${RESTFMDIRNAME}/${RESTFMBASENAME}"
    local DST="${RESTFMDIRNAME}/httpsRoot/${RESTFMBASENAME}"

    # If $DST exists.
    if [ -a "$DST" ]; then
        # If $DST is a symlink.
        if [ -h "$DST" ]; then
            # Remove existing symlink.
            rm -f "$DST"
            local RET=$?
            if [ "$RET" != "0" ]; then
                log_warning_msg "${MSGPREFIX} unable to remove existing"
                return
            fi
        else
            # Else we don't touch this, just issue a warning.
            log_warning_msg "${MSGPREFIX} path already exists"
            return
        fi
    fi

    # If we get here, we can create the new symlink.
    ln -s "$SRC" "$DST"
    local RET=$?
    if [ "$RET" != "0" ]; then
        log_warning_msg "${MSGPREFIX} failed to create"
        exit 1
    fi

    log_success_msg "${MSGPREFIX} done"
}

##
# Update the FMS Apache httpd.conf file to include the RESTfm .conf
#
updateFMSApacheConfig() {
    local MSGPREFIX="Update FMS Apache config:"
    local CONFBASENAME="/Library/FileMaker Server/HTTPServer/conf"
    local SRC="${CONFBASENAME}/httpd.conf"
    local DST="${CONFBASENAME}/httpd.conf.RESTfm.${TIMESTAMP}.bak"
    local INCLUDESTR="Include conf/extra/httpd-RESTfm.${RESTFMPATHMD5}.conf"

    # Check if we have this entry already.
    grep -q "$INCLUDESTR" "$SRC"
    if [ "$?" == "0" ]; then
        log_success_msg "${MSGPREFIX} already included"
        return
    fi

    cp "${SRC}" "${DST}"
    local RET=$?

    if [ "$RET" != "0" ]; then
        log_failure_msg "${MSGPREFIX} error backing up original file"
        exit 1
    fi

    # Append include string to FMS Apache config.
    echo "$INCLUDESTR" >> "${SRC}"

    log_success_msg "${MSGPREFIX} updated"
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

check_Location

check_Privilege

check_Y "Type Y to continue with installation, anything else will abort."

installRESTfmApacheConfig

updateHttpsRootSymlink

updateFMSApacheConfig

restartFMSApache

check_RESTfmReport

echo "Done."
echo

exit
