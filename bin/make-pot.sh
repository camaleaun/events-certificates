#!/usr/bin/env bash
# Regenerates languages/events-certificates.pot: the PHP, and the strings of the
# built scripts (build/, build-blocks/, build-signer/: run `npm run build` first,
# the editor's texts are in them). Sources and the third-party libraries are left out.
#
#   bin/make-pot.sh                    write the .pot, with `wp`
#   bin/make-pot.sh studio wp          write it on WordPress Studio (from the site root)
#   bin/make-pot.sh --check            fail when the committed .pot is out of date
#                                      (dates and file:line references are ignored)
#   WP="php -d memory_limit=1G $(command -v wp)" bin/make-pot.sh --check
#                                      WP-CLI is $WP when it is set (a container with little memory)
#
# `composer check` and the CI run --check.
#
# The pt_BR files are not in the repository (translate.wordpress.org has them);
# to try the Portuguese tests, merge the new template into the site's copy:
#   msgmerge --update --no-fuzzy-matching wp-content/languages/plugins/events-certificates-pt_BR.po languages/events-certificates.pot
#   (translate the new strings, then msgfmt -o ...pt_BR.mo ...pt_BR.po and `wp i18n make-php wp-content/languages/plugins`)
set -euo pipefail

plugin="$(cd "$(dirname "$0")/.." && pwd)"
pot="$plugin/languages/events-certificates.pot"

check=0
if [ "${1:-}" = "--check" ]; then
	check=1
	shift
fi
if [ -n "${WP:-}" ]; then
	read -r -a wp <<< "$WP"
else
	wp=("${@:-wp}")
fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

"${wp[@]}" i18n make-pot "$plugin" "$tmp/events-certificates.pot" \
	--domain=events-certificates \
	--include=includes,events-certificates.php,blocks,build,build-blocks,build-signer \
	--exclude=includes/third-party > /dev/null

if [ "$check" -eq 1 ]; then
	# The strings and their comments, without what changes on every run.
	normalize() {
		grep -v -e '^#: ' -e '^"Project-Id-Version: ' -e '^"POT-Creation-Date: ' -e '^"X-Generator: ' "$1" || true
	}
	if [ ! -f "$pot" ] || ! diff -q <( normalize "$pot" ) <( normalize "$tmp/events-certificates.pot" ) > /dev/null; then
		echo "languages/events-certificates.pot is out of date: run bin/make-pot.sh (after npm run build)." >&2
		diff <( normalize "$pot" 2> /dev/null ) <( normalize "$tmp/events-certificates.pot" ) | head -20 >&2 || true
		exit 1
	fi
	echo "languages/events-certificates.pot is up to date ($(grep -c '^msgid ' "$pot") strings)."
	exit 0
fi

mkdir -p "$plugin/languages"
cp "$tmp/events-certificates.pot" "$pot"
echo "Wrote languages/events-certificates.pot ($(grep -c '^msgid ' "$pot") strings)."
