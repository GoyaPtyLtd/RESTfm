#!/usr/bin/env bash
#
# This bash script builds the RESTfm distribution packages.
#
# RESTfm - FileMaker RESTful Web Service
#
# @copyright
#  Copyright (c) 2011-2017 Goya Pty Ltd.
#
# @license
#  Licensed under The MIT License. For full copyright and license information,
#  please see the LICENSE file distributed with this package.
#  Redistributions of files must retain the above copyright notice.
#
# @link
#  http://restfm.com
#
# @author
#  Gavin Stewart
#

# Identify precisely where we are.
ARGV0=`basename $0`
cd `dirname $0`
BASEDIR=`pwd`

### Configurable variables ###

# Package base name.
PACKAGEBASE="RESTfm"

# Package release version.
PACKAGERELEASE=`php lib/RESTfm/Version.php -r`

# Directory where Build files are placed.
BUILDDIR="Build"

# Directory where packages are placed.
PACKAGEDIR="Packages"

# Manifest list of files to include in distribution.
MANIFEST="MANIFEST"

# Files to inject SVN revision into (replaces %%REVISION%%)
INJECTREVISION="lib/RESTfm/Version.php"

# Files to inject Version into (replaces %%VERSION%%)
INJECTVERSION="RESTfm.ini.php.dist demo.html js/demo.js"

# PHPUnit command.
PHPUNIT="PHPUnit/phpunit --bootstrap lib/autoload.php"

# PHPUnit html coverage report directory.
PHPUNITCOVERAGEDIR="unit_coverage_report"

### Script begins ###

#
# script usage.
#
usage() {
    echo ""
    echo "Usage: ${ARGV0} [<flags>]"
    echo "  -b | --build            :  build distribution packages."
    echo "  -c | --clean            : clean intermediate build files."
    echo "       --clean-all        : expunge entire build area."
    echo "  -u | --unit             : unit tests only."
    echo "       --unit-coverage    : unit tests coverage only."
    echo ""
}

#
# Unit tests. Will exit on failure.
#
# @param $1 enableCoverage
#   Optional. Set to "enableCoverage" to enable PHPUnit html code coverage
#   report.
#
unitTests() {
    if [ "X${1}" == "XenableCoverage" ]; then
        echo " ++ Unit tests with code coverage report: ${PHPUNITCOVERAGEDIR}"
        ${PHPUNIT} --coverage-html ${PHPUNITCOVERAGEDIR}
        RET=$?
    else
        echo " ++ Unit tests."
        ${PHPUNIT}
        RET=$?
    fi
    if [ $RET -ne 0 ]; then
        echo "** Error: all unit tests must pass."
        exit 1
    fi
    echo " ++ Unit tests OK."
}

if [ $# -le 0 ]; then
    usage
    exit 1
fi

#
# Parse command line arguments.
#
while [ $# -ge 1 ]; do
    case "$1" in
    -b|--build)
        FULL_BUILDDIR="1"
        ;;
    -c|--clean)
        CLEAN="1"
        ;;
    --clean-all)
        CLEAN_ALL="1"
        ;;
    -u|--unit)
        unitTests
        exit 0
        ;;
    --unit-coverage)
        unitTests enableCoverage
        exit 0
        ;;
    *)
        usage
        exit 2
        ;;
    esac
    shift
done

# Path for fresh build, ready for packaging.
FRESHDIR="${BUILDDIR}/${PACKAGEBASE}"

#
# Clean
#
if [ ! -z "${CLEAN}" ]; then
    rm -rf "${FRESHDIR}"
    exit
fi

#
# Clean All
#
if [ ! -z "${CLEAN_ALL}" ]; then
    rm -rf "${BUILDDIR}"
    exit
fi

#
# Build
#
if [ -z "${FULL_BUILDDIR}" ]; then
    exit
fi

unitTests

# Ensure we are where we need to be by locating the manifest.
if [ ! -r "${MANIFEST}" ]; then
    echo "** Error: failed to locate ${MANIFEST}"
    exit 1
fi

# Determine git revision.
DATESTAMP=`date "+%Y%m%d"`
REPOREVISION=$(cd "${BASEDIR}"; git rev-parse --short HEAD)
if [ $? -ne 0 ]; then
    echo "** Error: git must be installed."
    exit 1
fi
REVISION="${DATESTAMP}-${REPOREVISION}"
VERSION="${PACKAGERELEASE}/${REVISION}"

# Check we have a full release, or a beta where we attach a revision
# to the release name.
echo "${PACKAGERELEASE}" | grep -q "^[0-9].[0-9].[0-9]$"
RET=$?
if [ "$RET" != "0" ]; then
    PACKAGERELEASE="${PACKAGERELEASE}-${REVISION}"
fi

echo " ++ Building ${PACKAGEBASE} ${PACKAGERELEASE}"

echo " ++ Remove old build area: ${FRESHDIR}"

# Clean and setup fresh build area.
rm -rf "${FRESHDIR}"
mkdir -p "${FRESHDIR}"

echo " ++ Creating fresh build: ${FRESHDIR}"

# Use manifest to create fresh build.
tar -cf - --exclude='.svn' --exclude='.git' --exclude='.DS_Store' -T "${MANIFEST}" | tar -xf - -C "${FRESHDIR}"

# Inject REVISION info.
if [ ! -z "${INJECTREVISION}" ]; then
    for INJECTFILE in ${INJECTREVISION}; do
        if [ -r "${FRESHDIR}/${INJECTFILE}" ]; then
            sed "s/%%REVISION%%/${REVISION}/g" "${FRESHDIR}/${INJECTFILE}" > "${FRESHDIR}/${INJECTFILE}._sed_"
            mv "${FRESHDIR}/${INJECTFILE}._sed_" "${FRESHDIR}/${INJECTFILE}"
        fi
    done
fi

# Inject VERSION info.
if [ ! -z "${INJECTVERSION}" ]; then
    for INJECTFILE in ${INJECTVERSION}; do
        if [ -r "${FRESHDIR}/${INJECTFILE}" ]; then
            sed "s_%%VERSION%%_${VERSION}_g" "${FRESHDIR}/${INJECTFILE}" > "${FRESHDIR}/${INJECTFILE}._sed_"
            mv "${FRESHDIR}/${INJECTFILE}._sed_" "${FRESHDIR}/${INJECTFILE}"
        fi
    done
fi

# Remove ".dist" suffix on any files.
find "${FRESHDIR}" -name "*.dist" | while read f; do mv "$f" "${f%.dist}"; done

echo " ++ Creating packages: ${PACKAGEDIR}"

mkdir -p "${PACKAGEDIR}"
PACKFQPN="${BASEDIR}/${PACKAGEDIR}/${PACKAGEBASE}-${PACKAGERELEASE}"

# Tar package.
tar czf "${PACKFQPN}.tgz" --exclude='.DS_Store' -C "${BUILDDIR}" --numeric-owner "${PACKAGEBASE}"

# Zip package.
(cd "${BUILDDIR}"; zip -r -x "*.DS_Store" --quiet "${PACKFQPN}.zip" "${PACKAGEBASE}")

echo " ++ Done building ${PACKAGEBASE} ${PACKAGERELEASE}"
