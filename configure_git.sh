#!/bin/bash

if [ "$TRAVIS_COMMITTER_EMAIL" == "" ]; then
    TRAVIS_COMMITTER_EMAIL="hello@matomo.org"
fi

if [ "$TRAVIS_COMMITTER_NAME" == "" ]; then
    TRAVIS_COMMITTER_NAME="Matomo Automation"
fi

echo "Configuring git [email = $TRAVIS_COMMITTER_EMAIL, user = $TRAVIS_COMMITTER_NAME]..."

git config --global user.email "$TRAVIS_COMMITTER_EMAIL"
git config --global user.name "$TRAVIS_COMMITTER_NAME"