#!/bin/bash

# Install git lfs for UI tests screenshots
# TODO: remove/update when Travis updates the VM (should be installed by default)
curl -sLo - https://github.com/github/git-lfs/releases/download/v1.2.1/git-lfs-linux-amd64-1.2.1.tar.gz | tar xzvf -
cd git-lfs-*

sudo ./install.sh
cd ..
rm -rf git-lfs-*

if [ "$TEST_SUITE" = "UITests" ];
then

    # Now that git-lfs is installed we download the screenshots
    sudo git lfs fetch
    sudo git lfs checkout

fi
