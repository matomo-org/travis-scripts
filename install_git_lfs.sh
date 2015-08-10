#!/bin/bash

set -e

# Install git lfs for UI tests screenshots
# TODO: remove/update when Travis updates the VM (should be installed by default)
if [ "$TEST_SUITE" = "UITests" ];
then

    # Change the remote because git lfs doesn't support git:// URLs
    # TODO remove the $GITHUB_USER_TOKEN once v0.6.0 is released
    git remote set-url origin "https://$GITHUB_USER_TOKEN:@github.com/$TRAVIS_REPO_SLUG.git"

    curl -sLo - https://github.com/github/git-lfs/releases/download/v0.5.4/git-lfs-linux-amd64-0.5.4.tar.gz | tar xzvf -
    cd git-lfs-*

    # We overwrite the shebang of the file waiting for a new git-lfs release which
    # contains https://github.com/github/git-lfs/commit/dbf09f71735e0456c7a707bba634016f66dfb1f7
    # TODO remove once a version > 0.5.4 is released
    sed "1s/.*/\#\!\/usr\/bin\/env bash/" install.sh > install.sh
    cat install.sh

    sudo ./install.sh
    cd ..
    rm -rf git-lfs-*

    # Now that git-lfs is installed we download the screenshots
    git lfs fetch

fi
