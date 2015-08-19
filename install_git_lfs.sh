#!/bin/bash

# Install git lfs for UI tests screenshots
# TODO: remove/update when Travis updates the VM (should be installed by default)
if [ "$TEST_SUITE" = "UITests" ];
then

    # Change the remote because git lfs doesn't support git:// URLs for now
    # TODO remove the $GITHUB_USER_TOKEN once v0.6.0 is released
    git remote set-url origin "https://$GITHUB_USER_TOKEN:@github.com/$TRAVIS_REPO_SLUG.git"

    go_version=$(go version | sed 's/go version go//; s/ .*//')
    echo $go_version
    if [[ $go_version < 1.3.1 ]]; then
        [[ -r ~/.gvm/scripts/gvm ]] ||
            bash < <(curl -s -S -L https://raw.githubusercontent.com/moovweb/gvm/master/binscripts/gvm-installer)
        set +u
        source ~/.gvm/scripts/gvm
        gvm install go1.4.2
        gvm use go1.4.2
        set -u
    fi

    CWD=$(pwd)

    cd /tmp
    git clone https://github.com/github/git-lfs
    cd git-lfs

    ./script/bootstrap
    sudo install -D bin/git-lfs /usr/local/bin
    git lfs init

    cd $CWD

    # Now that git-lfs is installed we download the screenshots
    git lfs fetch

fi
