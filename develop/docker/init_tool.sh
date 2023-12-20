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
declare I_AM='init_tool'
declare INIT_SLEEP_PID=0
declare WATCH_PROCESS_PID=0
declare WATCH_PROCESS_PIDFILE=/run/watch_process.pid
declare INOTIFYWAIT_PID=0

# Cleanup handler when running as init
init_cleanup() {
    log "Cleanup on signal: $1"
    # Unset trapped signals
    trap - INT TERM EXIT
    log 'Stopping fmshelper service'
    do_systemctl_stop fmshelper
    if [[ $INIT_SLEEP_PID != 0 ]]; then
        kill "$INIT_SLEEP_PID"
        wait "$INIT_SLEEP_PID"
    fi
    log 'Exiting'
    exit 0
}

# Basic init setup - trap signals, run the watch process.
setup_init() {
    log "I am: init" "$@"

    log 'Trapping signals'
    for SIG in INT TERM EXIT; do
        trap "init_cleanup $SIG" "$SIG"
    done

    log 'Starting path watch process'
    restart_watch_process
}

# Run as init - we just start fmshelper and wait forever
do_init() {
    setup_init "$@"

    log 'Starting fmshelper'
    do_systemctl_start fmshelper

    log 'Sleeping'
    # Interruptible sleep
    sleep infinity &
    INIT_SLEEP_PID=$!

    wait "${INIT_SLEEP_PID}"
}

# Just return true
# No output as it is parsed by deb postinst
do_firewall_cmd() {
    exit 0
}

# Just return true
# FMS installer: sysctl -p --system > /dev/null 2>&1
do_sysctl() {
    log "I am: sysctl " "$@"
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
        log "No service file found for: ${unit}"
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

# Reload handler for watch process
#
# Globals:
#   INOTIFYWAIT_PID
watch_process_reload() {
    log "Reloading on signal: $1"
    # Unset trap
    trap - HUP

    # Kill the inotifywait process, will be reaped in watch_process loop
    if [[ $INOTIFYWAIT_PID != 0 ]]; then
        kill "$INOTIFYWAIT_PID"
    fi
}

# Cleanup handler when running as watch process
watch_process_cleanup() {
    log "Cleanup on signal: $1"

    # Unset trapped signals
    trap - INT TERM EXIT

    if [[ $INOTIFYWAIT_PID != 0 ]]; then
        kill "$INOTIFYWAIT_PID"
        wait "$INOTIFYWAIT_PID"
    fi

    rm -f "${WATCH_PROCESS_PIDFILE}"

    log 'Exiting'
    exit 0
}

# Watch paths in SYSTEMD_PATH_WATCH, and call appropriate systemctl service.
# This function is backgrounded and managed by restart_watch_process()
#
# Globals:
#   SYSTEMD_PATH_WATCH[]
watch_process() {
    I_AM="watch_process"
    log 'Watch process starting'

    log 'Trapping signals'
    for SIG in INT TERM EXIT; do
        trap "watch_process_cleanup $SIG" "$SIG"
    done

    echo "$BASHPID" > "${WATCH_PROCESS_PIDFILE}"

    while :; do
        log 'Load systemd path files'
        load_systemd_path_files

        INOTIFYWAIT_PID=0

        trap "watch_process_reload HUP" "HUP"

        # Build list of watched parent paths for inotifywait
        local watch_path parent_path
        declare -A parent_paths=()
        for watch_path in "${!SYSTEMD_PATH_WATCH[@]}"; do
            parent_path="$(dirname "$watch_path")"
            parent_paths["$parent_path"]=1
        done

        if [[ ${#parent_paths[@]} -gt 0 ]]; then
            log "Running inotifywait on: " "${!parent_paths[@]}"
            (
                inotifywait -m -e modify --format '%w|%e|%f' "${!parent_paths[@]}" |
                    while IFS='|' read -r directory event file; do
                        log "Notified on: $directory $event $file"
                        if [[ -v "${SYSTEMD_PATH_WATCH["$directory/$file"]}" ]]; then
                            do_systemctl_start "${SYSTEMD_PATH_WATCH["$directory/$file"]}" 
                        else
                            log "Ignoring notification for: $directory/$file"
                        fi
                    done
            ) &
            INOTIFYWAIT_PID=$!
        else
            log "No paths to watch, sleeping"
            sleep infinity &
            INOTIFYWAIT_PID=$!
        fi

        wait "${INOTIFYWAIT_PID}"

    done
}

# Restart/start watch process
#
# Globals:
#   WATCH_PROCESS_PID
restart_watch_process() {
    if [[ $WATCH_PROCESS_PID != 0 ]]; then
        kill "${WATCH_PROCESS_PID}"
        wait "${WATCH_PROCESS_PID}"
    fi

    watch_process &
    WATCH_PROCESS_PID=$!
}

# Send signal to watch process to reload
#
signal_reload_watch_process() {
    if ! [[ -r "${WATCH_PROCESS_PIDFILE}" ]]; then
        log "Error: Cannot read watch process PID file: ${WATCH_PROCESS_PIDFILE}"
        restart_watch_process
        return
    fi

    local pid
    read -r pid < "${WATCH_PROCESS_PIDFILE}"
    kill -HUP "${pid}"
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
        log "No path file found for: ${unit}"
        return
    fi

    # Load the associated .service file for this .path file
    load_systemd_service "$unit"

    local temp

    temp=$(grep ^PathModified= "$unitfile")
    temp=${temp#PathModified=}
    SYSTEMD_PATH_WATCH[$temp]=$unit
}

# Load all *.path files found in /etc/systemd/system/
# SYSTEMD_* globals are cleared first
#
# Globals:
#   SYSTEMD_SERVICE_START[]
#   SYSTEMD_SERVICE_STOP[]
#   SYSTEMD_SERVICE_RELOAD[]
#   SYSTEMD_PATH_WATCH[]
load_systemd_path_files() {
    SYSTEMD_SERVICE_START=()
    SYSTEMD_SERVICE_STOP=()
    SYSTEMD_SERVICE_RELOAD=()
    SYSTEMD_PATH_WATCH=()

    local pathfile
    for pathfile in "/etc/systemd/system/"*.path; do
        load_systemd_path "$(basename "$pathfile")"
    done
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

do_systemctl_enable_path() {
    local unit=$1

    signal_reload_watch_process
}

do_systemctl() {
    log "I am: systemctl" "$@"

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
            log "Doing nothing for systemctl $command, returning true"
            ;;
        is-active)
            log "Doing nothing for systemctl $command, returning true"
            ;;
        is-enabled)
            log "Doing nothing for systemctl $command, returning true"
            ;;
        enable)
            if [[ "$unit" =~ \.path$ ]]; then
                unit="${unit%.path}"
                do_systemctl_enable_path "$unit"
            else
                log "Doing nothing for systemctl $command, returning true"
            fi
            ;;
        disable)
            log "Doing nothing for systemctl $command, returning true"
            ;;
        start)
            if [[ "$unit" =~ \.path$ ]]; then
                log "Doing nothing for systemctl $command, returning true"
            else
                unit="${unit%.service}"
                do_systemctl_start "$unit"
            fi
            ;;
        stop)
            if [[ "$unit" =~ \.path$ ]]; then
                log "Doing nothing for systemctl $command, returning true"
            else
                unit="${unit%.service}"
                do_systemctl_stop "$unit"
            fi
            ;;
        reload)
            if [[ "$unit" =~ \.path$ ]]; then
                log "Doing nothing for systemctl $command, returning true"
            else
                unit="${unit%.service}"
                do_systemctl_reload "$unit"
            fi
            ;;
        status)
            log "Doing nothing for systemctl $command, returning true"
            ;;
        *)
            log "Don't know systemctl command: $command, returning true anyway"
            ;;
    esac

    exit 0
}

# Install self as replacement for system tools in container
install_self() {
    log "Install self"

    # Safety check
    if [[ ! -f "/.dockerenv" ]]; then
        log "ERROR: Cannot detect we are inside a docker container" >&2
        exit 1
    fi

    local file
    for file in "${REPLACES[@]}"; do
        log "Replace $file"
        mv "$file" "$file.orig"
        ln -s "/init_tool.sh" "$file"
    done
}

# Install the fms package
install_fms() {
    local installer=$1
    shift

    # Set ourselves up as init
    setup_init "$@"

    log "Installing filemaker-server package: $installer"
    local parent_dir
    parent_dir="$(dirname "${installer}")"
    TERM=vt100 FM_ASSISTED_INSTALL="${parent_dir}" apt-get install -y "${installer}"
    log "Finished installing filemaker-server package"
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

# Set up to log file descriptor 5 to file and stdout
setup_log() {
    exec 5> >(tee -ia "/init_tool.log")
}

# Log provided message on param $1 to file descriptor 5
log() {
    echo "${I_AM}[$BASHPID]" "$1" >&5
}

# This comment intentionally blank
main() {
    setup_log

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

    exit 0
}

main "$@"
