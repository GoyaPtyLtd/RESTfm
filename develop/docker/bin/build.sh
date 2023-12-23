#!/bin/bash

# Build docker images for RESTfm and FileMaker Server

# Identify our exact location and ensure we are in the correct directory.
RELDIR=$(dirname "$0")
cd "${RELDIR}"/.. || exit 1

# Load common environment vars
source .env

# Sanity checks, make sure needed files exist
echo
echo "** Checking for required files"
REQUIRED_FILES=(
    ".env"
    "./files/fms/installer/Assisted Install.txt"
    "./files/fms/installer/LicenseCert.fmcert"
    "./files/certs/certificate.key"
    "./files/certs/certificate.pem"
    "./files/certs/certificate.pass"
)
MISSING_COUNT=0
for filename in "${REQUIRED_FILES[@]}"; do
    if test -f "$filename"; then
        echo "Found: $filename"
    else
        echo "Missing: $filename"
        MISSING_COUNT=$(($MISSING_COUNT + 1))
    fi
done
if [[ $MISSING_COUNT -gt 1 ]]; then
    echo "!! Error required file(s) missing"
    exit 1
fi
echo

echo "** Checking for filemaker-server v${FMS_VERSION} .deb file"
FMS_INSTALLER_COUNT=0
for filename in "./files/fms/installer/filemaker-server-${FMS_VERSION}."*".deb"; do
    echo "Found: $filename"
    FMS_INSTALLER_COUNT=$(($FMS_INSTALLER_COUNT + 1))
done
if [[ $FMS_INSTALLER_COUNT -eq 0 ]]; then
    echo "!! Error required file missing"
    exit 1
fi
if [[ $FMS_INSTALLER_COUNT -gt 1 ]]; then
    echo "!! Error $FMS_INSTALLER_COUNT matching .deb files for ${FMS_VERSION}, there can be only one."
    exit 1
fi
echo

# Find which docker compose to use ...
echo "** Finding docker compose"
DOCKER_COMPOSE=()
if docker compose --help | grep -q "Run 'docker compose COMMAND --help'"; then
    # docker has a built in compose, this is the preference
    DOCKER_COMPOSE=( docker compose )
elif which docker-compose; then
    # docker doesn't have a built in compose, but we found docker-compose
    DOCKER_COMPOSE=( docker-compose )
else
    echo "!! Error: no suitable docker compose found"
    exit 1
fi
echo

# Build it!
echo "** Building fms docker image"
BUILDKIT_PROGRESS=plain "${DOCKER_COMPOSE[@]}" build --pull fms
