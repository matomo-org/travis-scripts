#!/bin/bash
if [ "$SKIP_PIWIK_TEST_PREPARE" == "1" ]; then
    echo "Skipping Piwik specific test peparation."
    exit 0;
fi

set -e

# Install fonts for UI tests
if [ "$TEST_SUITE" = "UITests" ];
then
    mkdir $HOME/.fonts
    cp ./tests/travis/fonts/* $HOME/.fonts
    fc-cache -f -v

    echo "fonts:"
    ls $HOME/.fonts
fi

# quick fix for https://github.com/piwik/travis-scripts/issues/15
git checkout master ./tests/PHPUnit/config.ini.travis.php

# Copy Piwik configuration
echo "Install config.ini.php"
sed "s/PDO\\\MYSQL/${MYSQL_ADAPTER}/g" ./tests/PHPUnit/config.ini.travis.php > ./config/config.ini.php

# Prepare phpunit.xml
echo "Adjusting phpunit.xml"
cp ./tests/PHPUnit/phpunit.xml.dist ./tests/PHPUnit/phpunit.xml

if grep "@REQUEST_URI@" ./tests/PHPUnit/phpunit.xml > /dev/null; then
    sed -i 's/@REQUEST_URI@/\//g' ./tests/PHPUnit/phpunit.xml
fi

if [ -n "$PLUGIN_NAME" ];
then
      sed -n '/<filter>/{p;:a;N;/<\/filter>/!ba;s/.*\n/<whitelist addUncoveredFilesFromWhitelist=\"true\">\n<directory suffix=\".php\">..\/..\/plugins\/'$PLUGIN_NAME'<\/directory>\n<exclude>\n<directory suffix=\".php\">..\/..\/plugins\/'$PLUGIN_NAME'\/tests<\/directory>\n<directory suffix=\".php\">..\/..\/plugins\/'$PLUGIN_NAME'\/Test<\/directory>\n<directory suffix=\".php\">..\/..\/plugins\/'$PLUGIN_NAME'\/Updates<\/directory>\n<\/exclude>\n<\/whitelist>\n/};p' ./tests/PHPUnit/phpunit.xml > ./tests/PHPUnit/phpunit.xml.new && mv ./tests/PHPUnit/phpunit.xml.new ./tests/PHPUnit/phpunit.xml
fi;

# If we have a test suite remove code coverage report
#if [ -n "$TEST_SUITE" ]
#then
    # TODO: use php to remove this.
	# xmlstarlet ed -L -d "//phpunit/logging/log[@type='coverage-html']" ./tests/PHPUnit/phpunit.xml
#fi

# Create tmp/ sub-directories
mkdir -p ./tmp/assets
mkdir -p ./tmp/cache
mkdir -p ./tmp/latest
mkdir -p ./tmp/logs
mkdir -p ./tmp/sessions
mkdir -p ./tmp/templates_c
mkdir -p ./tmp/tcpdf
mkdir -p ./tmp/climulti
chmod a+rw ./tests/lib/geoip-files || true
chmod a+rw ./plugins/*/tests/System/processed || true
chmod a+rw ./plugins/*/tests/Integration/processed || true

# install phpredis
if [[ "$TRAVIS_PHP_VERSION" == 7* ]];
then
    echo "Ignoring installation of Redis for PHP 7 as it is not supported by phpredis or Travis yet"
else
    echo 'extension="redis.so"' > ./tmp/redis.ini
    phpenv config-add ./tmp/redis.ini
fi;
