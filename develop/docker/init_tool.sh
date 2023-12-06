#!/bin/bash

# Init tool for FileMaker Server in a Docker Container.
#
# This script replaces several system tools that FileMaker Server and it's
# installer are dependent upon.

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

REPLACES=(
  '/usr/bin/firewall-cmd'
  '/usr/bin/systemctl'
  '/usr/sbin/init'
  '/usr/sbin/sysctl'
)

# Just return true
do_firewall_cmd() {
    echo "I am: firewall-cmd " "$@"
    exit 0
}

do_init() {
    echo "I am: init" "$@"

    local pathfile
    for pathfile in "/etc/systemd/system/"*.path; do
        load_systemd_path "$(basename "$pathfile")"
    done

    echo "Sleeping forever"
    sleep infinity
}

# Just return true
# FMS installer: sysctl -p --system > /dev/null 2>&1
do_sysctl() {
    echo "I am: sysctl " "$@"
    exit 0
}

# Load systemd service file
#
# Globals:
#   SYSTEMD_SERVICE_START[unit]
#   SYSTEMD_SERVICE_STOP[unit]
#   SYSTEMD_SERVICE_RELOAD[unit]
load_systemd_service() {
    local unit=$1
    local unitfile=''

    # Only looking in /etc/systemd/system
    if [[ -f "/etc/systemd/system/${unit}" ]]; then
        unitfile="/etc/systemd/system/${unit}"
        unit="${unit%.service}"
    elif [[ -f "/etc/systemd/system/${unit}.service" ]]; then
        unitfile="/etc/systemd/system/${unit}.service"
    fi

    if [[ -z "$unitfile" ]]; then
        echo "ERROR: No service file found for: ${unit}" >&2
        exit 1
    fi

    local temp

    temp=$(grep ^ExecStart= "$unitfile")
    SYSTEMD_SERVICE_START[$unit]=${temp#ExecStart=}

    temp=$(grep ^ExecStop= "$unitfile")
    SYSTEMD_SERVICE_STOP[$unit]=${temp#ExecStop=}

    temp=$(grep ^ExecReload= "$unitfile")
    SYSTEMD_SERVICE_RELOAD[$unit]=${temp#ExecReload=}
}

# Load systemd path file
#
# Globals:
#   SYSTEMD_PATH_MODIFIED[unit]
load_systemd_path() {
    local unit=$1
    local pathfile=''

    # Only looking in /etc/systemd/system
    unit="${unit%.path}"
    if [[ -f "/etc/systemd/system/${unit}.path" ]]; then
        pathfile="/etc/systemd/system/${unit}.path"
    fi

    if [[ -z "$pathfile" ]]; then
        echo "ERROR: No service path found for: ${unit}" >&2
        exit 1
    fi

    load_systemd_service "$unit"

    local temp

    temp=$(grep ^PathModified= "$unitfile")
    SYSTEMD_PATH_WATCH[$unit]=${temp#PathModified=}
}

do_systemctl_start() {
    local unit=$0

    load_systemd_service "$unit"
    eval "${SYSTEMD_SERVICE_START[$unit]}"
}

do_systemctl_stop() {
    local unit=$0

    load_systemd_service "$unit"
    eval "${SYSTEMD_SERVICE_STOP[$unit]}"
}

do_systemctl_reload() {
    local unit=$0

    load_systemd_service "$unit"
    eval "${SYSTEMD_SERVICE_RELOAD[$unit]}"
}

do_systemctl_start_path() {
    local unit=$0

    load_systemd_path "$unit"
}

do_systemctl_stop_path() {
    local unit=$0
    load_systemd_path "$unit"
}

do_systemctl() {
    echo "I am: systemctl" "$@"

    local command command_switches unit
    command=$1
    shift
    command_switches=()
    while [[ "$1" =~ ^-.* ]]; do
        command_switches+=("$1")
        shift
    done
    unit=$1

    case "$command" in
        daemon-reexec|daemon-reload)
            echo "Doing nothing for systemctl $command, returning true"
            ;;
        is-active)
            echo "Doing nothing for systemctl $command, returning true"
            ;;
        is-enabled)
            echo "Doing nothing for systemctl $command, returning true"
            ;;
        enable)
            echo "Doing nothing for systemctl $command, returning true"
            ;;
        disable)
            echo "Doing nothing for systemctl $command, returning true"
            ;;
        start)
            if [[ "$unit" =~ \.path$ ]]; then
                do_systemctl_start_path "$unit"
            else
                do_systemctl_start "$unit"
            fi
            ;;
        stop)
            if [[ "$unit" =~ \.path$ ]]; then
                do_systemctl_stop_path "$unit"
            else
                do_systemctl_stop "$unit"
            fi
            ;;
        status)
            echo "Doing nothing for systemctl $command, returning true"
            ;;
        *)
            echo "Don't know systemctl command: $command, returning true anyway"
            ;;
    esac

    exit 0
}

# Install self as replacement for system tools in container
install_self() {
    echo "Self install"

    # Safety check
    if [[ ! -f "/.dockerenv" ]]; then
        echo "ERROR: Cannot detect we are inside a docker container" >&2
        exit 1
    fi

    local file
    for file in "${REPLACES[@]}"; do
        echo "Replace $file"
        mv "$file" "$file.orig"
        ln -s "/init_tool.sh" "$file"
    done
}

do_default() {
    local param=$1
    shift

    case "$param" in
        --install)
            install_self
            ;;
    esac
}

# main

# Log all output to file.
exec >  >(trap "" INT TERM; sed 's/^/ ++ /' | tee -ia "/init_tool.log")
exec 2> >(trap "" INT TERM; sed 's/^/ !! /' | tee -ia "/init_tool.log" >&2)

# Find how we were called
I_AM=$(basename "$0")

case "$I_AM" in
    firewall-cmd)
        do_firewall_cmd "$@"
        ;;

    init)
        do_init "$@"
        ;;

    sysctl)
        do_sysctl "$@"
        ;;

    systemctl)
        do_systemctl "$@"
        ;;

    *)
        do_default "$@"
        ;;
esac
