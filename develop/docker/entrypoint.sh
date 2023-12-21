#!/bin/bash

# Entrypoint for FileMaker Server in Docker container

echo " ++ Started: $(basename "$0")"

cleanup() {
    echo " ++ Cleanup on signal: $1"
    # Unset trapped signals
    trap - INT TERM EXIT
    echo ' ++ Stopping fmshelper service'
    systemctl stop fmshelper
    echo " ++ Exit"
    exit 0
}

trap_cleanup() {
    for SIG in INT TERM EXIT; do
        trap "cleanup $SIG" "$SIG"
    done
    # List trapped signals
    trap
}

echo ' ++ Setting cleanup trap'
trap_cleanup

nginx() {
    case "$1" in
        start)
            echo " ++ Starting nginx ..."
            /usr/sbin/nginx -c "/opt/FileMaker/FileMaker Server/NginxServer/conf/fms_nginx.conf"
            echo " ++ ... done"
            ;;
        stop)
            echo " ++ Stopping nginx ..."
            pkill nginx
            echo " ++ ... done"
            ;;
        restart)
            echo " ++ Restarting nginx ..."
            pkill nginx; sleep 1; /usr/sbin/nginx -c "/opt/FileMaker/FileMaker Server/NginxServer/conf/fms_nginx.conf"
            echo " ++ ... done"
            ;;
        graceful)
            echo " ++ Reloading nginx ..."
            /usr/sbin/nginx -c "/opt/FileMaker/FileMaker Server/NginxServer/conf/fms_nginx.conf" -s reload
            echo " ++ ... done"
            ;;
    esac
}

apache() {
    case "$1" in
        start)
            ;;
        stop)
            ;;
        restart)
            ;;
        graceful)
            ;;
    esac
}

webserver_controller() {
    echo ' ++ Webserver Controller waiting on FMS webserver control files'
    pushd '/opt/FileMaker/FileMaker Server/' > /dev/null || exit
    inotifywait -m -e modify --format '%w|%e|%f' 'NginxServer/' 'HTTPServer/' |
        while IFS='|' read -r directory event file; do
            echo " ++ Notified on: $directory $event $file"
            case "$directory" in
                NginxServer/)
                    nginx "$file"
                    ;;
                HTTPServer/)
                    apache "$file"
                    ;;
            esac
        done
}

# Run the webserver controller in the background
#webserver_controller &
#sleep 1

# First start of fmshelper with no certificate
echo ' ++ Starting fmshelper - no SSL certificate'
systemctl start fmshelper

echo " ++ Installing self-signed certificate ..."
fmsadmin certificate import -y -u Admin -p password \
    /etc/ssl/certs/ssl-cert-snakeoil.pem \
    --keyfile /etc/ssl/ssl-cert-snakeoil-enc.key  \
    --keyfilepass password
echo " ++ ... done"

# Second start of fmshelper with self-signed certificate
echo " ++ Restarting fmshelper"
systemctl restart fmshelper

# Insert notification that entrypoint is complete
echo " ++ Ready"
{ echo "READY"; date; } > '/opt/FileMaker/FileMaker Server/NginxServer/htdocs/httpsRoot/ready.html'

sleep infinity
