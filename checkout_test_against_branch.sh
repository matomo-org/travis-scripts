#!/bin/bash
# NOTE: this script is executed with Matomo master checked out. After it is run, Matomo master may not be checked out.

SCRIPT_DIR=`dirname $0`

if [ "$TEST_AGAINST_PIWIK_BRANCH" == "" ]; then
    if [ "$TEST_AGAINST_CORE" == "latest_stable" ]; then # test against the latest stable release of Matomo core (including betas & release candidates)
        # keeping latest_stable enabled until all plugins successfully migrated
        export TEST_AGAINST_PIWIK_BRANCH=$(git describe --tags `git rev-list --tags --max-count=1`)
        export TEST_AGAINST_PIWIK_BRANCH=`echo $TEST_AGAINST_PIWIK_BRANCH | tr -d ' ' | tr -d '\n'`

        #echo "Testing against 'latest_stable' is no longer supported, please test against 'minimum_required_piwik'."
        #exit 1
    elif [[ "$TEST_AGAINST_CORE" == "minimum_required_piwik" && "$PLUGIN_NAME" != "" ]]; then # test against the minimum required Piwik in the plugin.json file
        export TEST_AGAINST_PIWIK_BRANCH=$(php "$SCRIPT_DIR/get_required_matomo_version.php" $PLUGIN_NAME)

        if ! git rev-parse "$TEST_AGAINST_PIWIK_BRANCH" >/dev/null 2>&1
        then
            echo "Could not find tag '$TEST_AGAINST_PIWIK_BRANCH' specified in plugin.json, testing against 4.x-dev."

            export TEST_AGAINST_PIWIK_BRANCH=4.x-dev
        fi
    fi
elif [[ "$TEST_AGAINST_PIWIK_BRANCH" == "maximum_supported_piwik" && "$PLUGIN_NAME" != "" ]]; then # test against the maximum supported Matomo in the plugin.json file
    export TEST_AGAINST_PIWIK_BRANCH=$(php "$SCRIPT_DIR/get_required_matomo_version.php" $PLUGIN_NAME "max")

    if ! git rev-parse "$TEST_AGAINST_PIWIK_BRANCH" >/dev/null 2>&1
    then
        echo "Could not find tag '$TEST_AGAINST_PIWIK_BRANCH' specified in plugin.json, testing against 4.x-dev."

        export TEST_AGAINST_PIWIK_BRANCH=4.x-dev
    fi
fi

echo "Testing against '$TEST_AGAINST_PIWIK_BRANCH'"
rm -rf ./tests/travis
git reset --hard
if ! git checkout "$TEST_AGAINST_PIWIK_BRANCH" --force; then
    echo ""
    echo "Failed to checkout $TEST_AGAINST_PIWIK_BRANCH"
    echo "git status:"
    echo ""

    git status

    exit 1
fi

echo "Initializing submodules"
git submodule init -q
git submodule update -q || true

echo "Making sure travis-scripts submodule is being used"
if [ ! -d ./tests/travis/.git ]; then
    echo "Older Matomo w/o travis-scripts submodule, checking out."

    rm -rf ./tests/travis

    if ! git clone https://github.com/matomo-org/travis-scripts.git ./tests/travis; then
        exit 1
    fi
fi

cd tests/travis

echo "Fetching travis-scripts branches"
git fetch origin

echo "Checking for travis-scripts branch to use..."
if git ls-remote --exit-code . "origin/$TEST_AGAINST_PIWIK_BRANCH" ; then
    echo "Found travis-scripts branch or tag corresponding to TEST_AGAINST_PIWIK_BRANCH environment variable, checking out '$TEST_AGAINST_PIWIK_BRANCH' for tests."

    git checkout "origin/$TEST_AGAINST_PIWIK_BRANCH"
else
    echo "Available branches:"

    git branch -a
fi

cd ../..
