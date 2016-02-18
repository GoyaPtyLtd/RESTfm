#!/bin/bash

# RESTfm install script for use on Mac OS X where FileMaker Server has
# been deployed on the same machine.
#
# Supported Operating System versions:
#   Mac OS X 10.10 Yosemite
#   Mac OS X 10.9 Mavericks
#
# Supported software versions:
#   FileMaker Server 14
#   FileMaker Server 13
#
# Copyright (C) 2016, Goya Pty. Ltd.
# All rights reserved.

# Ensure we have the correct privilege
if [ "$EUID" != "0" ]; then
    exec sudo -p 'Type your password to allow RESTfm install, %u:' bash "$0" "$@"
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

show_Header

date

check_OSXVersion

check_FMSVersion

check_ApacheVersion

check_FMS_WPE_PHP

check_Location

check_Privilege

check_Y "Type Y to continue with installation, anything else will abort."

install_RESTfmApacheConfig

install_HttpsRootSymlink

update_FMSApacheConfig

update_RESTfmHtaccess

update_RESTfmIni

restart_FMSApache

check_RESTfmReport

echo "Done."
echo

exit
