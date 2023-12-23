#!/bin/bash

# Install SSL/TLS Certificate into FileMaker Server, once only.
#
# Note: It is not possible to install a certificate during docker image build
#       as the encryption key that FMS uses (presumably a hash of something
#       like hostname) is not repeatable when running the container later.
#       We install the certificate at container runtime to resolve this issue.

READY_FILE='/opt/FileMaker/FileMaker Server/NginxServer/htdocs/httpsRoot/ready.txt'

# Don't run again if we have already run
if [[ -e "${READY_FILE}" ]]; then
    echo " ++ Certificate installation has already run"
    exit 0
fi

# Pull the FMS Admin Console user and password from Assisted Install.txt
FMS_AC_USER="$(grep "Admin Console User=" "/fms/Assisted Install.txt")"
FMS_AC_USER="${FMS_AC_USER#*=}"
FMS_AC_PASS="$(grep "Admin Console Password=" "/fms/Assisted Install.txt")"
FMS_AC_PASS="${FMS_AC_PASS#*=}"

echo " ++ Installing SSL/TLS certificate ..."
fmsadmin_code=-999
attempt_count=1
while [[ $fmsadmin_code -ne 0 && $attempt_count -le 10 ]]; do

    fmsadmin_output=$(fmsadmin certificate import -y -u "${FMS_AC_USER}" -p "${FMS_AC_PASS}" \
        /certs/certificate.pem \
        --keyfile /certs/certificate.key  \
        --keyfilepass "$(cat /certs/certificate.pass)" 2>&1)
    fmsadmin_code=$?

    if [[ $fmsadmin_code -ne 0 ]]; then
        echo "  ++ fmsadmin certificate import failed attempt: ${attempt_count}"

        if [[ "$fmsadmin_output" =~ .*"Error: 9"|"10502".* ]]; then
            # Error: 9 (Access denied)
            # Error: 10502 (Host unreachable)
            #
            # These are common errors on slower systems while fmshelper
            # starts up, so we ignore and try again. Don't show the error.
            :
        else
            # Output this fmsadmin error message though, and try again.
            echo "  !! Unexpected error: $fmsadmin_code: $fmsadmin_output"
        fi

        ((attempt_count+=1))
        sleep 2
    else
        # Success!
        echo "${fmsadmin_output}"
        break
    fi

done

if [[ $fmsadmin_code -ne 0 ]]; then
    echo " !! Failed to install SSL/TLS certificate, last error was:"
    echo "$fmsadmin_output"
    exit 1
fi

echo " ++ ... done"

echo " ++ Restarting fmshelper"
systemctl restart fmshelper

# Save knowledge that we have run
echo " ++ Ready"
{ echo "READY"; date; } > "${READY_FILE}"

