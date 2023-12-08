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

# System files that this script replaces
REPLACES=(
  '/usr/bin/firewall-cmd'
  '/usr/bin/systemctl'
  '/usr/sbin/init'
  '/usr/sbin/sysctl'
)

# Global variables
declare -A SYSTEMD_SERVICE_START=()
declare -A SYSTEMD_SERVICE_STOP=()
declare -A SYSTEMD_SERVICE_RELOAD=()
declare -A SYSTEMD_PATH_WATCH=()
declare WATCH_DAEMON_PID=0

# Cleanup handler when running as init
init_cleanup() {
    echo "Init cleanup on signal: $1"
    # Unset trapped signals
    trap - INT TERM EXIT
    echo 'Stopping fmshelper service'
    do_systemctl_stop fmshelper
    echo 'Exiting'
    exit 0
}

# Run as init
do_init() {
    echo "I am: init" "$@"

    echo 'Trapping signals'
    for SIG in INT TERM EXIT; do
        trap "init_cleanup $SIG" "$SIG"
    done

    echo 'Loading systemd path files'
    local pathfile
    for pathfile in "/etc/systemd/system/"*.path; do
        load_systemd_path "$(basename "$pathfile")"
    done

    echo 'Starting systemd path watch daemon'
    restart_watch_daemon

    echo 'Starting fmshelper'
    do_systemctl_start fmshelper

    echo 'Sleeping forever'
    sleep infinity
}

# Just return true
# No output as it is parsed by deb postinst
do_firewall_cmd() {
    exit 0
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
    if [[ -f "/etc/systemd/system/${unit}.service" ]]; then
        unitfile="/etc/systemd/system/${unit}.service"
    fi

    if [[ -z "$unitfile" ]]; then
        echo "No service file found for: ${unit}"
        return
    fi

    local temp

    temp=$(grep ^ExecStart= "$unitfile")
    SYSTEMD_SERVICE_START[$unit]=${temp#ExecStart=}

    temp=$(grep ^ExecStop= "$unitfile")
    SYSTEMD_SERVICE_STOP[$unit]=${temp#ExecStop=}

    temp=$(grep ^ExecReload= "$unitfile")
    SYSTEMD_SERVICE_RELOAD[$unit]=${temp#ExecReload=}
}

# Cleanup handler when running as watch daemoin
watch_daemon_cleanup() {
    echo "Watch daemon cleanup on signal: $1"
    # Unset trapped signals
    trap - INT TERM EXIT
    echo 'Exiting'
    exit 0
}

# Watch paths in SYSTEMD_PATH_WATCH, and call appropriate systemctl service.
# This function is backgrounded and managed by restart_watch_daemon()
#
# Globals:
#   SYSTEMD_PATH_WATCH[]
watch_daemon() {
    BASH_ARGV0='watch_daemon'
    echo 'Watch daemon starting'

    echo 'Trapping signals'
    for SIG in INT TERM EXIT; do
        trap "watch_daemon_cleanup $SIG" "$SIG"
    done

    # Build list of watched parent paths for inotifywait
    local watch_path, parent_path
    declare -A parent_paths=()
    for watch_path in "${!SYSTEMD_PATH_WATCH[@]}"; do
        parent_path="$(dirname "$watch_path")"
        parent_paths["$parent_path"]=1
    done

    echo "Running inotifywait on: " "${!parent_paths[@]}"
    inotifywait -m -e modify --format '%w|%e|%f' "${!parent_paths[@]}" |
        while IFS='|' read -r directory event file; do
            echo "Notified on: $directory $event $file"
            if [[ -v "${SYSTEMD_PATH_WATCH["$directory/$file"]}" ]]; then
                do_systemctl_start "${SYSTEMD_PATH_WATCH["$directory/$file"]}" 
            else
                echo "Ignoring notification for: $directory/$file"
            fi
        done
}

# Restart/start watch daemon
#
# Globals:
#   WATCH_DAEMON_PID
restart_watch_daemon() {
    if [[ $WATCH_DAEMON_PID != 0 ]]; then
        kill "${WATCH_DAEMON_PID}"
        wait "${WATCH_DAEMON_PID}"
    fi

    watch_daemon &
    WATCH_DAEMON_PID=$!
}

# Load systemd path from systemd path file into SYSTEMD_PATH_WATCH[]
#
# Globals:
#   SYSTEMD_PATH_WATCH[]
load_systemd_path() {
    local unit=$1
    local pathfile=''

    # Only looking in /etc/systemd/system
    if [[ -f "/etc/systemd/system/${unit}.path" ]]; then
        pathfile="/etc/systemd/system/${unit}.path"
    fi

    if [[ -z "$pathfile" ]]; then
        echo "No path file found for: ${unit}"
        return
    fi

    load_systemd_service "$unit"

    local temp

    temp=$(grep ^PathModified= "$unitfile")
    temp=${temp#PathModified=}
    SYSTEMD_PATH_WATCH[$temp]=$unit
}

# Unload systemd path from SYSTEMD_PATH_WATCH[] by unit
#
# Globals:
#   SYSTEMD_PATH_WATCH[]
unload_systemd_path() {
    local unit=$1

    # Walk list of watched paths, remove any for this unit
    local watch_path
    for watch_path in "${!SYSTEMD_PATH_WATCH[@]}"; do
        if [[ "${SYSTEMD_PATH_WATCH[$watch_path]}" -eq "$unit" ]]; then
            unset "SYSTEMD_PATH_WATCH[$watch_path]"
        fi
    done
}

do_systemctl_start() {
    local unit=$1

    load_systemd_service "$unit"
    if [[ -v "${SYSTEMD_SERVICE_START[$unit]}" ]]; then
        eval "${SYSTEMD_SERVICE_START[$unit]}"
    fi
}

do_systemctl_stop() {
    local unit=$1

    load_systemd_service "$unit"
    if [[ -v "${SYSTEMD_SERVICE_STOP[$unit]}" ]]; then
        eval "${SYSTEMD_SERVICE_STOP[$unit]}"
    fi
}

do_systemctl_reload() {
    local unit=$1

    load_systemd_service "$unit"
    if [[ -v "${SYSTEMD_SERVICE_RELOAD[$unit]}" ]]; then
        eval "${SYSTEMD_SERVICE_RELOAD[$unit]}"
    fi
}

do_systemctl_start_path() {
    local unit=$1

    load_systemd_path "$unit"

    restart_watch_daemon
}

do_systemctl_stop_path() {
    local unit=$1

    unload_systemd_path "$unit"

    restart_watch_daemon
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
                unit="${unit%.path}"
                do_systemctl_start_path "$unit"
            else
                unit="${unit%.service}"
                do_systemctl_start "$unit"
            fi
            ;;
        stop)
            if [[ "$unit" =~ \.path$ ]]; then
                unit="${unit%.path}"
                do_systemctl_stop_path "$unit"
            else
                unit="${unit%.service}"
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
    echo "Install self"

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

# Install the fms package
install_fms() {
    local installer=$1
    shift

    local parent_dir="$(dirname "${installer}")"
    TERM=vt100 FM_ASSISTED_INSTALL="${parent_dir}" apt-get install -y "${installer}"
}

do_default() {
    local param=$1
    shift

    case "$param" in
        --install-self)
            install_self
            ;;
        --install-fms)
            install_fms "$@"
            ;;
    esac
}

# main

# Log all output to file.
exec >  >(trap "" INT TERM; sed 's/^/ ++ /' | tee -ia "/init_tool.log")
exec 2> >(trap "" INT TERM; sed 's/^/ !! /' | tee -ia "/init_tool.log" >&2)

#echo "DEBUG: $0" "$@"

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
