#!/bin/bash

# Install SSL/TLS Certificate into FileMaker Server, once only.
#
# Note: It is not possible to install a certificate during docker image build
#       as the encryption key that FMS uses (presumably a hash of something
#       like hostname) is not repeatable when running the container later.
#       We install the certificate at container runtime to resolve this issue.

READY_FILE='/opt/FileMaker/FileMaker Server/NginxServer/htdocs/httpsRoot/ready.html'

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
fmsadmin certificate import -y -u "${FMS_AC_USER}" -p "${FMS_AC_PASS}" \
    /certs/certificate.pem \
    --keyfile /certs/certificate.key  \
    --keyfilepass "$(cat /certs/certificate.pass)"
echo " ++ ... done"

echo " ++ Restarting fmshelper"
systemctl restart fmshelper

# Save knowledge that we have run
echo " ++ Ready"
{ echo "READY"; date; } > "${READY_FILE}"

