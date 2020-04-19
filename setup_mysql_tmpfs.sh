#!/bin/bash

if [ "$SKIP_PIWIK_TEST_PREPARE" == "1" ] || [ "$TEST_SUITE" == "AngularJSTests" ] || [ "$TEST_SUITE" == "UnitTests" ] || [ "$TEST_SUITE" == "JavascriptTests" ]; then
    echo "Skipping mysql setup."
    exit 0;
fi

if [[ "$TRAVIS_SUDO" == "true" ]]
then
    sudo mkdir /mnt/ramdisk
    sudo mount -t tmpfs -o size=1024m tmpfs /mnt/ramdisk
    if [[ "$TRAVIS_DIST" == "bionic" ]] || [[ "$TRAVIS_DIST" == "xenial" ]]
    then
        sudo systemctl stop mysql
    else
        sudo stop mysql
    fi
    sudo mv /var/lib/mysql /mnt/ramdisk
    sudo ln -s /mnt/ramdisk/mysql /var/lib/mysql
    if [[ "$TRAVIS_DIST" == "bionic" ]] || [[ "$TRAVIS_DIST" == "xenial" ]]
    then
        sudo systemctl start mysql
    else
        sudo start mysql
    fi
fi

# print out mysql information
mysql --version
mysql -e "SELECT VERSION();"

# configure mysql
mysql -e "SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION,STRICT_TRANS_TABLES'" # Travis default
# try to avoid 'mysql has gone away' errors
mysql -e "SET GLOBAL wait_timeout = 36000;"
mysql -e "SET GLOBAL max_allowed_packet = 134209536;"
mysql -e "SHOW VARIABLES LIKE 'max_allowed_packet';"
mysql -e "SHOW VARIABLES LIKE 'wait_timeout';"

mysql -e "SELECT @@sql_mode;"
# - mysql -e "SHOW GLOBAL VARIABLES;"

# print out more debugging info
uname -a
date
php -r "var_dump(gd_info());"
mysql -e 'create database piwik_tests;'