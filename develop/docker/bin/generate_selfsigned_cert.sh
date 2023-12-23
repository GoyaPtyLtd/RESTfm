#!/bin/bash

# Generate a self signed certificate for use in containers

# Identify our exact location and ensure we are in the correct directory.
RELDIR=$(dirname "$0")
cd "${RELDIR}"/.. || exit 1

CERT_CN="restfm.dev"
CERT_O="RESTfm Development"
CERT_C="AU"

echo " ++ Self-signed Certificate Settings"
echo -n "    Passphrase: "
read -r -s pass1
echo
echo -n "        Verify: "
read -r -s pass2
echo
if [[ -z "${pass1}" || "${pass1}" != "${pass2}" ]]; then
    echo " !! Error: passphrase empty or mismatch"
    exit 1
fi
echo -n "    Common Name [${CERT_CN}]: "
read -r response
CERT_CN=${response:-$CERT_CN}
echo -n "    Organisation [${CERT_O}]: "
read -r response
CERT_O=${response:-$CERT_O}
echo -n "    Country Code [${CERT_C}]: "
read -r response
CERT_C=${response:-$CERT_C}

echo " ++ building self signed certificate"
echo "${pass1}" > ./files/certs/certificate.pass
chmod o-rw ./files/certs/certificate.pass
openssl req -subj "/CN=${CERT_CN}/O=${CERT_O}/C=${CERT_C}" -new \
        -newkey rsa:2048 -days 365 -x509 \
        -keyout ./files/certs/certificate.key \
        -out ./files/certs/certificate.pem \
        -passout file:./files/certs/certificate.pass

