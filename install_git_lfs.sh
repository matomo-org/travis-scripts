#!/bin/bash

command -v 'git-lfs' >/dev/null || {
  GIT_LFS_VERSION=2.2.1

  curl -sLo - https://github.com/github/git-lfs/releases/download/v${GIT_LFS_VERSION}/git-lfs-linux-amd64-${GIT_LFS_VERSION}.tar.gz | tar xzvf -

  mkdir -p "${HOME}/bin"
  mv git-lfs-*/git-lfs "${HOME}/bin"
  rm -rf git-lfs-*

  export PATH="${HOME}/bin:$PATH"
}

if [[ "${TEST_SUITE}" == "UITests" ]]; then
    git lfs fetch --exclude=
    git lfs checkout
fi
