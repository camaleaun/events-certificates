#!/usr/bin/env bash
# Puts the plugin, as committed (git archive HEAD) plus what the build makes, into a Studio site. Run the build first
# (npm run build; bin/install.sh for the third-party libraries). The same method as bin/package.sh, without the zip.
#
#   bin/demo/deploy.sh ~/Studio/evcert-demo-standalone
set -euo pipefail

plugin="$(cd "$(dirname "$0")/../.." && pwd)"
site="${1:?the path of the Studio site}"
slug="events-certificates"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

cd "$plugin"
mkdir -p "$tmp/$slug"
git archive --format=tar HEAD | tar -x -C "$tmp/$slug"
for dir in build build-signer build-blocks assets/css assets/js assets/third-party includes/third-party; do
	mkdir -p "$tmp/$slug/$dir"
	cp -R "$dir/." "$tmp/$slug/$dir/"
done
find "$tmp" \( -name '*.map' -o -name '.DS_Store' \) -delete
mkdir -p "$site/wp-content/plugins/$slug"
rsync -a --delete "$tmp/$slug/" "$site/wp-content/plugins/$slug/"
echo "deployed to $site"
