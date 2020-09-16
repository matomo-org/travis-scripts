#!/bin/bash

if [ "$TEST_SUITE" = "SystemTests" ] || [ "$TEST_SUITE" = "PluginTests" ] || [ "$TEST_SUITE" = "SystemTestsCore" ] || [ "$TEST_SUITE" = "SystemTestsPlugins" ];
then

    echo "Uploading artifacts for $TEST_SUITE..."

    if [ "$TEST_SUITE" = "SystemTestsCore" ];
    then
        url="https://builds-artifacts.matomo.org/build?auth_key=$ARTIFACTS_PASS&repo=$TRAVIS_REPO_SLUG&artifact_name=system&branch=$TRAVIS_BRANCH&build_id=$TRAVIS_BUILD_NUMBER"
        tar -cjf processed.tar.bz2 tests/PHPUnit/System/processed/* --exclude='.gitkeep' --transform 's/.*\///'
    else
        if [ "$TEST_SUITE" = "PluginTests" ];
        then
            url="https://builds-artifacts.matomo.org/build?auth_key=$ARTIFACTS_PASS&repo=$TRAVIS_REPO_SLUG&artifact_name=system&branch=$TRAVIS_BRANCH&build_id=$TRAVIS_BUILD_NUMBER"
            if [ "$PROTECTED_ARTIFACTS" = "1" ];
            then
                echo "Artifacts will be protected (premium plugin)..."
                url="$url&protected=1"
            fi
            tar -cjf processed.tar.bz2 plugins/$PLUGIN_NAME/tests/System/processed/* --exclude='.gitkeep' --transform "s/plugins\/$PLUGIN_NAME\/tests\/System\/processed\///"
        else
            url="https://builds-artifacts.matomo.org/build?auth_key=$ARTIFACTS_PASS&repo=$TRAVIS_REPO_SLUG&artifact_name=system.plugin&branch=$TRAVIS_BRANCH&build_id=$TRAVIS_BUILD_NUMBER"
            tar -cjf processed.tar.bz2 plugins/*/tests/System/processed/* --exclude='.gitkeep' --transform 's/plugins\///g' --transform 's/\/tests\/System\/processed\//~~/'
        fi
    fi

    # upload processed tarball
    curl -X POST --data-binary @processed.tar.bz2 "$url"
else
    if [ "$TEST_SUITE" = "UITests" ];
    then
        url_base="https://builds-artifacts.matomo.org/build?auth_key=$ARTIFACTS_PASS&repo=$TRAVIS_REPO_SLUG&build_id=$TRAVIS_BUILD_NUMBER&build_entity_id=$TRAVIS_BUILD_ID&branch=$TRAVIS_BRANCH"

        if [ -n "$PLUGIN_NAME" ];
        then
            if [ "$PROTECTED_ARTIFACTS" = "1" ];
            then
                echo "Artifacts will be protected (premium plugin)..."
                url_base="$url_base&protected=1"
            fi
        fi

        echo "Uploading artifacts for $TEST_SUITE..."

        base_dir=`pwd`
        if [ -n "$PLUGIN_NAME" ];
        then
            if [ -d "./plugins/$PLUGIN_NAME/Test/UI" ]; then
                cd "./plugins/$PLUGIN_NAME/Test/UI"
            else
                cd "./plugins/$PLUGIN_NAME/tests/UI"
            fi
        else
            cd ./tests/UI
        fi

        echo "[NOTE] Processed Screenshots:"
        ls processed-ui-screenshots
        echo ""

        # upload processed tarball
        tar -cjf processed-ui-screenshots.tar.bz2 processed-ui-screenshots --exclude='.gitkeep'
        curl -X POST --data-binary @processed-ui-screenshots.tar.bz2 "$url_base&artifact_name=processed-screenshots"

        # upload diff tarball if it exists
        cd $base_dir/tests/UI
        if [ -d "./screenshot-diffs" ];
        then
            echo "Uploading artifacts..."

            echo "[NOTE] screenshot diff dir:"
            echo "`pwd`/screenshot-diffs"

            echo "[NOTE] uploading following diffs:"
            ls screenshot-diffs

            tar -cjf screenshot-diffs.tar.bz2 screenshot-diffs
            curl -X POST --data-binary @screenshot-diffs.tar.bz2 "$url_base&artifact_name=screenshot-diffs"
        fi
    else
        echo "No artifacts for $TEST_SUITE tests."
        exit
    fi
fi
