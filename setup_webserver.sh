#!/bin/bash
if [ "$SKIP_PIWIK_TEST_PREPARE" == "1" ]; then
    echo "Skipping webserver setup."
    exit 0;
fi

set -e

DIR=$(dirname "$0")
DIR=$(realpath "$DIR")

service nginx stop

# Setup PHP-FPM
echo "Configuring php-fpm"

if [[ "$TRAVIS_PHP_VERSION" == 5.3* ]];
then
    # path does not exist with 5.3.3 so use 5.3 
    PHP_FPM_BIN="$HOME/.phpenv/versions/5.3/sbin/php-fpm"
else
    PHP_FPM_BIN="$HOME/.phpenv/versions/$TRAVIS_PHP_VERSION/sbin/php-fpm"
fi;

PHP_FPM_CONF="$DIR/php-fpm.conf"
PHP_FPM_SOCK="$DIR/php-fpm.sock"

if [ -d "$TRAVIS_BUILD_DIR/../piwik/tmp/" ]; then
    PHP_FPM_LOG="$TRAVIS_BUILD_DIR/../piwik/tmp/php-fpm.log"
elif [ -d "$TRAVIS_BUILD_DIR/piwik/tmp/" ]; then
    PHP_FPM_LOG="$TRAVIS_BUILD_DIR/piwik/tmp/php-fpm.log"
elif [ -d "$TRAVIS_BUILD_DIR" ]; then
    PHP_FPM_LOG="$TRAVIS_BUILD_DIR/php-fpm.log"
else
    PHP_FPM_LOG="$HOME/php-fpm.log"
fi

USER=$(whoami)

echo "php-fpm user = $USER"

touch "$PHP_FPM_LOG"

# Adjust php-fpm.ini
sed -i "s/@USER@/$USER/g" "$DIR/php-fpm.ini"
sed -i "s|@PHP_FPM_SOCK@|$PHP_FPM_SOCK|g" "$DIR/php-fpm.ini"
sed -i "s|@PHP_FPM_LOG@|$PHP_FPM_LOG|g" "$DIR/php-fpm.ini"
sed -i "s|@PATH@|$PATH|g" "$DIR/php-fpm.ini"

# Setup nginx
echo "Configuring nginx"
PIWIK_ROOT=$(realpath "$DIR/../..")
NGINX_CONF="/etc/nginx/sites-enabled/default"

sed -i "s|@PIWIK_ROOT@|$PIWIK_ROOT|g" "$DIR/piwik_nginx.conf"
sed -i "s|@PHP_FPM_SOCK@|$PHP_FPM_SOCK|g" "$DIR/piwik_nginx.conf"

# Start daemons
echo "Starting php-fpm"
$PHP_FPM_BIN --fpm-config "$DIR/php-fpm.ini"

echo "Starting nginx using config $DIR/piwik_nginx.conf"
if grep "sudo: false" "$TRAVIS_BUILD_DIR/.travis.yml"; then
    nginx -c "$DIR/piwik_nginx.conf"
else
    # use port 80 if this build allows using sudo
    sed -i "s|listen\s*3000;|listen 80;|g" "$DIR/piwik_nginx.conf"
    echo "user www-data;" | cat - "$DIR/piwik_nginx.conf" > .tmpconf && mv .tmpconf "$DIR/piwik_nginx.conf"

    sudo chown www-data:www-data "$PHP_FPM_SOCK"
    sudo nginx -c "$DIR/piwik_nginx.conf"
fi