#!/bin/bash

# Install git lfs for UI tests screenshots
# TODO: remove/update when Travis updates the VM (should be installed by default)
if [ "$TEST_SUITE" = "UITests" ];
then

    # Change the remote because git lfs doesn't support git:// URLs
    # TODO remove the $GITHUB_USER_TOKEN once v0.6.0 is released
    git remote set-url origin "https://$GITHUB_USER_TOKEN:@github.com/$TRAVIS_REPO_SLUG.git"

    curl -sLo - https://github.com/github/git-lfs/releases/download/v0.5.2/git-lfs-linux-amd64-0.5.2.tar.gz | tar xzvf -
    cd git-lfs-*
    sudo ./install.sh
    cd ..
    rm -rf git-lfs-*

    # Now that git-lfs is installed we download the screenshots
    git lfs fetch

fi
