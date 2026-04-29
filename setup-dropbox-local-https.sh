#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CERT_DIR="$ROOT_DIR/docker/nginx/certs"
HOSTNAME="${1:-gooddads.localtest.me}"
PORT="${DROPBOX_AUTH_HTTPS_PORT:-8443}"

if ! command -v mkcert >/dev/null 2>&1; then
    echo "mkcert is required. Install it first: https://github.com/FiloSottile/mkcert"
    exit 1
fi

mkdir -p "$CERT_DIR"

mkcert -install
mkcert \
    -cert-file "$CERT_DIR/dropbox-auth.pem" \
    -key-file "$CERT_DIR/dropbox-auth-key.pem" \
    "$HOSTNAME" localhost 127.0.0.1 ::1

cat <<EOF
Local HTTPS certificate generated.

Use these values in your .env before testing Dropbox OAuth over HTTPS:
APP_URL=https://$HOSTNAME:$PORT
DROPBOX_AUTH_HTTPS_PORT=$PORT
DROPBOX_REDIRECT_URI=https://$HOSTNAME:$PORT/dropbox/callback
SESSION_SECURE_COOKIE=true

Then restart Sail and open:
https://$HOSTNAME:$PORT/dropbox/authorize

If curl still reports an issuer error on macOS, validate with:
curl --cacert "$(mkcert -CAROOT)/rootCA.pem" https://$HOSTNAME:$PORT/dropbox/authorize
EOF