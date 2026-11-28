#!/usr/bin/env bash
# Throwaway Studio sites for the screenshots (and a look at the product): one per case, never the development site.
# Each is made once, gets the plugin as committed (bin/demo/deploy.sh) and its made-up data (bin/demo/seed.php); the ids the
# screenshot manifest needs are written to site/.demo/<case>.json. Run from site/:
#
#   npm run demo-sites -- standalone|gatherpress [--fresh]        (--fresh deletes the site and starts over)
set -euo pipefail

case_="${1:?the case: standalone}"
fresh="${2:-}"
here="$(cd "$(dirname "$0")/.." && pwd)"
plugin="$(cd "$here/.." && pwd)"
path="${EVCERT_DEMO_ROOT:-$HOME/Studio}/evcert-demo-$case_"
port=""

case "$case_" in
	standalone|gatherpress) ;;
	*) echo "The demo site of '$case_' is not made yet." >&2; exit 1 ;;
esac

if [ "$fresh" = "--fresh" ] && [ -d "$path" ]; then
	studio site delete --path "$path" --files >/dev/null 2>&1 || true
fi
if [ ! -f "$path/wp-config.php" ]; then
	mkdir -p "$path"
	studio site create --path "$path" --name "Events Certificates demo: $case_" --skip-browser
fi
studio site start --path "$path" --skip-browser >/dev/null 2>&1 || true

"$plugin/bin/demo/deploy.sh" "$path"
# The integration of the case, at the version the plugin is tested with.
if [ "$case_" = gatherpress ]; then
	studio wp --path="$path" plugin install gatherpress --version=0.37.0 --activate
fi
studio wp --path="$path" plugin activate events-certificates
# A neutral administrator: the admin bar shows no one's name in a screenshot.
studio wp --path="$path" user update 1 --display_name="Organizer" --nickname="Organizer" >/dev/null
out="$(studio wp --path="$path" eval-file "$plugin/bin/demo/seed.php" "$case_" en | tail -1)"
mkdir -p "$here/.demo"
printf '%s\n' "${out#EVCERT_DEMO }" > "$here/.demo/$case_.json"
echo "$case_: $(cat "$here/.demo/$case_.json")"
