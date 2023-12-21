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
  '/usr/bin/cat'
)

# Global variables
declare -A SYSTEMD_SERVICE_START=()
declare -A SYSTEMD_SERVICE_STOP=()
declare -A SYSTEMD_SERVICE_RELOAD=()
declare -A SYSTEMD_PATH_WATCH=()
declare I_AM='init_tool'
declare INIT_SLEEP_PID=0
declare WATCH_PROCESS_PPID=0
declare WATCH_PROCESS_PIDFILE=/run/watch_process.pid
declare INOTIFYWAIT_PPID=0

# Cleanup handler when running as init
init_cleanup() {
    log "Cleanup on signal: $1"

    # block further signals
    trap '' INT TERM EXIT

    log 'Stopping fmshelper service'
    do_systemctl_stop fmshelper

    if [[ $INIT_SLEEP_PID != 0 ]]; then
        kill "$INIT_SLEEP_PID"
        wait "$INIT_SLEEP_PID"
    fi

    if [[ -r "${WATCH_PROCESS_PIDFILE}" ]]; then
        log 'Stopping path watch_process'
        local pid
        read -r pid < "${WATCH_PROCESS_PIDFILE}"
        kill "${pid}"
    fi

    log 'Exiting'
    exit 0
}

# Basic init setup - trap signals, run the watch process.
setup_init() {
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

# We need to violate /opt/FileMaker/etc/init.d/fmshelper which tries to
# check if it is installing in docker ... and fails miserably.
#
# Notes:
#   The init.d/fmshelper script will cat /proc/1/cgroup to check it contains
#   the string "docker", if it does it will then cat
#   /sys/fs/cgroup/memory/memory.limit_in_bytes which doesn't exist in
#   cgroup v2 and will cause the script to crash.
do_cat() {
    # This one case
    if [[ "$1" == "/proc/1/cgroup" ]]; then
        log "caught this one case!"
        echo "not this time"
        exit 0
    fi

    # Re-exec and pass through args to the original cat
    exec cat.orig "$@"
}

# Just return true
# No output as it is parsed by deb postinst
do_firewall_cmd() {
    exit 0
}

# Just return true
# FMS installer: sysctl -p --system > /dev/null 2>&1
do_sysctl() {
    log "Called as: sysctl" "$@"
    exit 0
}

# Load systemd service file
#
# Globals:
#   SYSTEMD_SERVICE_START[unit]
#   SYSTEMD_SERVICE_STOP[unit]
#   SYSTEMD_SERVICE_RELOAD[unit]
load_systemd_service() {
    local unit="${1%.service}"
    local unitfile=''

    # Only looking in /etc/systemd/system
    if [[ -e "/etc/systemd/system/${unit}.service" ]]; then
        unitfile="/etc/systemd/system/${unit}.service"
    fi

    if [[ -z "$unitfile" ]]; then
        log "No service file found for: ${unit}"
        return
    fi

    log "Load ${unit}.service"

    local temp

    temp=$(grep ^ExecStart= "$unitfile")
    temp=${temp#ExecStart=}                 # Remove "ExecStart=" prefix
    temp=${temp#-}                          # Remove possible "-" prefix
    SYSTEMD_SERVICE_START[$unit]=${temp}

    temp=$(grep ^ExecStop= "$unitfile")
    temp=${temp#ExecStop=}
    temp=${temp#-}
    SYSTEMD_SERVICE_STOP[$unit]=${temp}

    temp=$(grep ^ExecReload= "$unitfile")
    temp=${temp#ExecReload=}
    temp=${temp#-}
    SYSTEMD_SERVICE_RELOAD[$unit]=${temp#ExecReload=}
}

# Reload handler for watch process
#
# Globals:
#   INOTIFYWAIT_PPID
watch_process_reload() {
    log "Reloading on signal: $1"

    # Ignore re-signaling
    trap '' HUP

    # Kill the inotifywait process, will be reaped in watch_process loop
    if [[ $INOTIFYWAIT_PPID != 0 ]]; then
        log "Kill inotifywait PPID: ${INOTIFYWAIT_PPID}"
        pkill -P "${INOTIFYWAIT_PPID}"
    fi
}

# Cleanup handler when running as watch process
watch_process_cleanup() {
    log "Cleanup on signal: $1"

    # block further signals
    trap '' INT TERM EXIT

    if [[ $INOTIFYWAIT_PPID != 0 ]]; then
        pkill -P "$INOTIFYWAIT_PPID"
        wait "$INOTIFYWAIT_PPID"
    fi

    rm -f "${WATCH_PROCESS_PIDFILE}"

    log 'Exiting'
    exit 0
}

# A manual and more execv(3) intensive version of inotifywait(1).
# 'stat' is called for each found watch file every second.
#
# Output formatted as:
#   /opt/FileMaker/FileMaker Server/NginxServer/|MODIFY|start
#
# Parameters:
#   $@ - List of paths to files to watch for change in mtime
emulate_inotifywait() {
    local watch_paths=()
    local watch_dirnames=()
    local watch_basenames=()
    local watch_mtimes=()

    I_AM="${FUNCNAME[0]}"

    # Predetermine dirnames and basenames
    local watch_path
    for watch_path in "$@"; do
        watch_paths+=("$watch_path")
        watch_dirnames+=("$(dirname "$watch_path")/")
        watch_basenames+=("$(basename "$watch_path")")
    done

    # Identify mtimes of existing files before we start monitoring
    local i mtime
    for (( i=0; i<${#watch_paths[@]}; i++ )); do
        if [[ -e "${watch_paths[$i]}" ]]; then
            mtime=$(stat --format=%Y "${watch_paths[$i]}")

            watch_mtimes[$i]=$mtime
        else
            watch_mtimes[$i]=0
        fi
    done

    # Endless monitoring loop
    while :; do
        for (( i=0; i<${#watch_paths[@]}; i++ )); do
            if [[ -e "${watch_paths[$i]}" ]]; then
                mtime=$(stat --format=%Y "${watch_paths[$i]}")

                if [[ $mtime -gt ${watch_mtimes[$i]} ]]; then
                    printf '%s|MODIFY|%s\n' "${watch_dirnames[$i]}" "${watch_basenames[$i]}"
                    watch_mtimes[$i]=$mtime
                fi
            else
                watch_mtimes[$i]=0
            fi
        done
        sleep 1
    done
}

# Watch paths in SYSTEMD_PATH_WATCH, and call appropriate systemctl service.
# This function is backgrounded and managed by restart_watch_process()
#
# Globals:
#   SYSTEMD_PATH_WATCH[]
watch_process() {
    I_AM="${FUNCNAME[0]}"
    if [[ "${EMULATE_INOTIFYWAIT}" == "1" ]]; then
        log 'Watch process starting (emulated inotifywait)'
    else
        log 'Watch process starting (inotifywait)'
    fi

    log 'Trapping signals'
    for SIG in INT TERM EXIT; do
        trap "watch_process_cleanup $SIG" "$SIG"
    done

    echo "$BASHPID" > "${WATCH_PROCESS_PIDFILE}"

    while :; do
        log 'Load systemd path files'
        load_systemd_path_files

        INOTIFYWAIT_PPID=0

        trap "watch_process_reload HUP" "HUP"

        # Build list of watched parent paths for inotifywait
        local watch_path parent_path
        declare -A parent_paths=()
        for watch_path in "${!SYSTEMD_PATH_WATCH[@]}"; do
            parent_path="$(dirname "$watch_path")"
            parent_paths["$parent_path"]=1
        done

        if [[ ${#parent_paths[@]} -gt 0 ]]; then
            (
                log "Watching paths:" "${!parent_paths[@]}"
                {
                    if [[ "${EMULATE_INOTIFYWAIT}" == "1" ]]; then
                        emulate_inotifywait "${!SYSTEMD_PATH_WATCH[@]}"
                    else
                        # inotifywait output formatted as:
                        #   /opt/FileMaker/FileMaker Server/NginxServer/|MODIFY|start
                        #
                        # Testing notes:
                        #   - This will trigger two consecutive modify events:
                        #       echo "test" > /some/path/start
                        #   - This will trigger just one modify event (preferred):
                        #       echo "test" >> /some/path/start
                        inotifywait -m -e modify --format '%w|%e|%f' "${!parent_paths[@]}"
                    fi
                } |
                    while IFS='|' read -r directory event file; do
                        log "Notified on: ${directory}|${event}|${file}"
                        if [[ -v SYSTEMD_PATH_WATCH[${directory}${file}] ]]; then
                            do_systemctl_start "${SYSTEMD_PATH_WATCH[${directory}${file}]}"
                        else
                            log "Ignoring notification for: ${directory}${file}"
                        fi
                    done
            ) &
            INOTIFYWAIT_PPID=$!
        else
            (
                log "No paths to watch, sleeping"
                sleep infinity
            ) &
            INOTIFYWAIT_PPID=$!
        fi

        log "Waiting on inotifywait PPID: ${INOTIFYWAIT_PPID}"
        wait "${INOTIFYWAIT_PPID}"
    done
}

# Restart/start watch process
#
# Globals:
#   WATCH_PROCESS_PPID
restart_watch_process() {
    if [[ $WATCH_PROCESS_PPID != 0 ]]; then
        pkill -P "${WATCH_PROCESS_PPID}"
        wait "${WATCH_PROCESS_PPID}"
    fi

    watch_process &
    WATCH_PROCESS_PPID=$!
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

    # Slight delay, to slow down rapid re-HUPping
    sleep 0.2
}

# Load systemd path from systemd path file into SYSTEMD_PATH_WATCH[]
#
# Globals:
#   SYSTEMD_PATH_WATCH[]
load_systemd_path() {
    local unit="${1%.path}"
    local pathfile=''

    # Only looking in /etc/systemd/system
    if [[ -e "/etc/systemd/system/${unit}.path" ]]; then
        pathfile="/etc/systemd/system/${unit}.path"
    fi

    if [[ -z "$pathfile" ]]; then
        log "No path file found for: ${unit}"
        return
    fi

    log "Load ${unit}.path"

    local temp
    temp=$(grep ^PathModified= "$pathfile")
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

    log "Performing systemctl start: $unit"

    load_systemd_service "$unit"
    if [[ -v SYSTEMD_SERVICE_START[$unit] ]]; then
        log "Exec: ${SYSTEMD_SERVICE_START[$unit]}"
        eval "${SYSTEMD_SERVICE_START[$unit]}" 1>&5 2>&5
    fi

    if [[ "${unit%.service}" == "fmshelper" ]]; then
        if [[ "$INSTALL_FMS" == "1" ]]; then
            # This is a hack to trigger the start of the web server
            # Notes:
            #   For some reason (currently unknown) when the .deb is
            #   being installed while inside of the Dockerfile build
            #   environment, the "start" file is never written to the
            #   filesystem as normal. systemd would watch for this as
            #   defined in a .path file (and this script does the
            #   equivalent). We need another trigger, and inspection of
            #   the .deb postinst file gives us this one.
            #sleep 2
            #local crap=$(ps axf)
            #log "crap1: $crap"
            #systemctl start com.filemaker.nginx.start.service
            #sleep 2
            #crap=$(ps axf)
            #log "crap2: $crap"
            :
        fi
    fi
}

do_systemctl_stop() {
    local unit=$1

    log "Performing systemctl stop: $unit"

    load_systemd_service "$unit"
    if [[ -v SYSTEMD_SERVICE_STOP[$unit] ]]; then
        log "Exec: ${SYSTEMD_SERVICE_STOP[$unit]}"
        eval "${SYSTEMD_SERVICE_STOP[$unit]}" 1>&5 2>&5
    fi
}

do_systemctl_reload() {
    local unit=$1

    log "Performing systemctl reload: $unit"

    load_systemd_service "$unit"
    if [[ -v SYSTEMD_SERVICE_RELOAD[$unit] ]]; then
        log "Exec: ${SYSTEMD_SERVICE_RELOAD[$unit]}"
        eval "${SYSTEMD_SERVICE_RELOAD[$unit]}" 1>&5 2>&5
    fi
}

do_systemctl_restart() {
    local unit=$1

    log "Performing systemctl restart: $unit"

    load_systemd_service "$unit"
    if [[ -v SYSTEMD_SERVICE_RELOAD[$unit] ]]; then
        log "Exec: ${SYSTEMD_SERVICE_STOP[$unit]}"
        eval "${SYSTEMD_SERVICE_STOP[$unit]}" 1>&5 2>&5
        log "Exec: ${SYSTEMD_SERVICE_START[$unit]}"
        eval "${SYSTEMD_SERVICE_START[$unit]}" 1>&5 2>&5
    fi
}

do_systemctl_enable_path() {
    local unit=$1

    signal_reload_watch_process
}

do_systemctl() {
    log "Called as: systemctl" "$@"

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
        daemon-reexec)
            log "Doing nothing for systemctl $command, returning true"
            ;;
        daemon-reload)
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
        restart)
            if [[ "$unit" =~ \.path$ ]]; then
                log "Doing nothing for systemctl $command, returning true"
            else
                unit="${unit%.service}"
                do_systemctl_restart "$unit"
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
    if [[ ! -e "/.dockerenv" ]]; then
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

    # Tell other processes (children of us) that this is the FMS install
    export INSTALL_FMS=1

    # Set ourselves up as init
    setup_init "$@"

    log "Installing filemaker-server package: $installer"
    local parent_dir result
    parent_dir="$(dirname "${installer}")"
    TERM=vt100 FM_ASSISTED_INSTALL="${parent_dir}" apt-get install -y "${installer}"
    result=$?
    log "Finished installing filemaker-server package: $result"

    log "Restoring /usr/bin/cat"
    mv -f /usr/bin/cat.orig /usr/bin/cat

    exit $result
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

# Log provided message(s) in parameters to file descriptor 5
log() {
    echo "${I_AM}[$BASHPID]" "$@" >&5
}

# This comment intentionally blank
main() {
    setup_log

    I_AM=$(basename "$0")
    case "$I_AM" in
        cat)
            do_cat "$@"
            ;;
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