#!/bin/bash

# NOTE: should be removed when composer used to handle plugin dependencies

if [ "$DEPENDENT_PLUGINS" == "" ]; then
    echo "No dependent plugins."
else
    echo "Cloning dependent plugins..."
    echo ""
    
    for pluginSlug in $DEPENDENT_PLUGINS
    do
        dependentPluginName=`echo "$pluginSlug" | sed -E 's/[a-zA-Z0-9_-]+\/[a-zA-Z0-9_]+-(.*)/\1/'`

        echo "Cloning $pluginSlug into plugins/$dependentPluginName..."
        if [ "$GITHUB_USER_TOKEN" == "" ]; then
            git clone --depth=1 "https://github.com/$pluginSlug" "plugins/$dependentPluginName"
        else
            git clone --depth=1 "https://$GITHUB_USER_TOKEN:@github.com/$pluginSlug" "plugins/$dependentPluginName" 2>&1 | sed "s/$GITHUB_USER_TOKEN/GITHUB_USER_TOKEN/"
        fi
        
        rm -rf "plugins/$dependentPluginName/tests/Integration"
        rm -rf "plugins/$dependentPluginName/Test/Integration"
        rm -rf "plugins/$dependentPluginName/tests/Unit"
        rm -rf "plugins/$dependentPluginName/Test/Unit"
        rm -rf "plugins/$dependentPluginName/tests/System"
        rm -rf "plugins/$dependentPluginName/Test/System"
    done

    echo "Plugin directory:"
    echo ""

    ls -d plugins/*
fi
