#!/bin/bash

if [ "$TEST_SUITE" = "SystemTests" ];
then
    url="http://builds-artifacts.piwik.org/build?auth_key=$ARTIFACTS_PASS&repo=$TRAVIS_REPO_SLUG&artifact_name=system&branch=$TRAVIS_BRANCH&build_id=$TRAVIS_BUILD_NUMBER"

    echo "Uploading artifacts for $TEST_SUITE..."

    cd ./tests/PHPUnit/System

    # upload processed tarball
    tar -cjf processed.tar.bz2 processed --exclude='.gitkeep'
    curl -X POST --data-binary @processed.tar.bz2 "$url"
else
    if [ "$TEST_SUITE" = "UITests" ];
    then
        url_base="http://builds-artifacts.piwik.org/build?auth_key=$ARTIFACTS_PASS&repo=$TRAVIS_REPO_SLUG&build_id=$TRAVIS_BUILD_NUMBER&build_entity_id=$TRAVIS_BUILD_ID&branch=$TRAVIS_BRANCH"

        if [ -n "$PLUGIN_NAME" ];
        then
            if [ "$UNPROTECTED_ARTIFACTS" = "" ];
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
