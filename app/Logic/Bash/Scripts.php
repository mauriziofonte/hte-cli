<?php

namespace Mfonte\HteCli\Logic\Bash;

class Scripts
{
    /**
     * Get the Apache2 self-signed certificates directory.
     *
     * @return string
     */
    public static function getSelfSignedCertsDir() : string
    {
        return '/etc/apache2/certs-selfsigned';
    }
    
    /**
     * Generates a bash script to create a self-signed certificate for the given domain.
     *
     * @param string $domain
     * @param integer $days
     *
     * @return string
     */
    public static function getSelfSignedCertScript(string $domain, int $days = 10950) : string
    {
        $ssldir = self::getSelfSignedCertsDir();

        // if the directory doesn't exist, create it
        if (!is_dir($ssldir)) {
            mkdir($ssldir, 0755, true);
        }

        $script = <<<SCRIPT
#!/usr/bin/env bash

# Check that we're running as root
if [ "$(id -u)" != "0" ]; then
    echo >&2 "ERROR: This script must be run as root. Aborting."
    exit 1
fi

# Check for required commands
command -v openssl >/dev/null 2>&1 || { echo >&2 "ERROR: openssl binary is required but it's not installed. Aborting."; exit 1; }

# Generate a passphrase
export PASSPHRASE=$(head -c 500 /dev/urandom | tr -dc a-z0-9A-Z | head -c 128; echo)

# Runtime-compiled configuration file in a temporary location
CONFIG_FILE=$(mktemp /tmp/openssl.cnf.XXXXXX)

cat > \$CONFIG_FILE <<-EOF
[req]
default_bits = 2048
prompt = no
default_md = sha256
x509_extensions = v3_req
distinguished_name = dn

[dn]
C = IT
ST = Italy
L = Italy
O = Acme Corp
OU = The Dev Team
emailAddress = webmaster@$domain
CN = $domain

[v3_req]
subjectAltName = @alt_names

[alt_names]
DNS.1 = *.$domain
DNS.2 = $domain
EOF

# Remove previous keys
echo " > Removing existing previous self-signed certs with pattern $domain.*"
if ls $ssldir/$domain.* 1> /dev/null 2>&1; then
    rm -rf $ssldir/$domain.*
fi

echo " > Generating certs for $domain"

# Generate our Private Key, CSR and Certificate
# Use SHA-2 as SHA-1 is unsupported from Jan 1, 2017
echo " > Generating RSA private key, 2048 bit long modulus"
openssl req -new -x509 -newkey rsa:2048 -sha256 -nodes -keyout "$ssldir/$domain.key" -days $days -out "$ssldir/$domain.crt" -passin pass:\$PASSPHRASE -config "\$CONFIG_FILE" 2>/dev/null >/dev/null

# OPTIONAL - write an info to see the details of the generated crt
echo " > Writing info to $ssldir/$domain.info"
openssl x509 -noout -fingerprint -text < "$ssldir/$domain.crt" > "$ssldir/$domain.info"

# Protect the key
echo " > Protecting the key with chmod 400 $ssldir/$domain.key"
chmod 400 "$ssldir/$domain.key"

# Remove the config file
echo " > Removing the temporary config file \$CONFIG_FILE"
rm -f \$CONFIG_FILE

# Unset the passphrase
unset PASSPHRASE

# exit with success
exit 0

SCRIPT;

        return $script;
    }
}
