#!/bin/bash

# RESTfm uninstall script for use on Mac OS X where RESTfm has previously
# been installed by the partner install script.
#
# Copyright (C) 2016, Goya Pty. Ltd.
# All rights reserved.

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

setupLogfile

showHeaderUninstall

date

check_Location

check_Privilege

check_Installed
# DEBUG
#echo ${RESTFMCONFLIST[@]}

select_Uninstall

echo "Done."
echo

exit
