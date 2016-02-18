#!/bin/bash

# RESTfm uninstall script for use on Mac OS X where RESTfm has previously
# been installed by the partner install script.
#
# Copyright (C) 2016, Goya Pty. Ltd.
# All rights reserved.

# Ensure we have the correct privilege
if [ "$EUID" != "0" ]; then
    exec sudo -p 'Type your password to allow RESTfm uninstall, %u:' bash "$0" "$@"
fi

# Identify precisely where we are.
ARGV0=`basename "$0"`
RELDIR=`dirname "$0"`
cd "$RELDIR"
BASEDIR=`pwd`
ARGS=("$@")
LOGNAME=`logname`

### Global variables ###

# Define LSB log_* functions.
. "${BASEDIR}"/init-functions

# Load shared functions.
. "${BASEDIR}"/shared-functions

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

### Main ###

echo

setup_Logfile

setup_Traps

show_HeaderUninstall

date

check_OSXVersion

check_FMSVersion

check_ApacheVersion

check_Location

check_Privilege

check_Installed
# DEBUG
#echo ${RESTFM_CONF_LIST[@]}

select_Uninstall

echo

check_Y "Type Y to continue with uninstall, anything else will abort."

update_FMSApacheConfigRemove

if [[ ${RESTFM_UNINSTALL_BASE_NAME} != "Orphan entry"* ]]; then
    uninstall_RESTfmApacheConfig

    uninstall_HttpsRootSymlink
fi

restart_FMSApache

echo -n "Done."
if [[ ${RESTFM_UNINSTALL_BASE_NAME} != "Orphan entry"* ]]; then
    echo -n " The \"${RESTFM_UNINSTALL_BASE_NAME}\" folder may now be removed."
fi
echo

echo

exit
