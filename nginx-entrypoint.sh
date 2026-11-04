#!/bin/sh
set -e

if [ -n "$ADMIN_USER" ] && [ -n "$ADMIN_PASSWORD" ]; then
    printf "%s:%s\n" "$ADMIN_USER" "$(openssl passwd -apr1 "$ADMIN_PASSWORD")" > /etc/nginx/.htpasswd
    echo "[htpasswd] generated for user: $ADMIN_USER"
fi