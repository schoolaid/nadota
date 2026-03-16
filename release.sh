#!/bin/bash

set -e

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Uso: ./release.sh 1.0.11"
    exit 1
fi

# Update version in composer.json
sed -i '' "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" composer.json

echo "Version actualizada a $VERSION en composer.json"

# Commit
git add -A
git commit -m "Bump version to $VERSION"

# Tag and push
git tag "v$VERSION"
git push origin main --tags

echo "Release v$VERSION publicado"
