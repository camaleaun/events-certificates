#!/usr/bin/env bash
# After cloning: makes what the repository does not hold, so the plugin runs and the checks pass.
#
#   bin/install.sh                 composer and npm packages, the third-party libraries, the build
#   WP="studio wp" bin/install.sh  WP-CLI is $WP ("wp" by default)
#   EVCERT_NO_DEV=1 bin/install.sh only what ships (the release does this)
#
# What it makes:
#   vendor/                  Composer's packages: development tools and the libraries' sources (never shipped)
#   node_modules/            npm's packages
#   includes/third-party/    dompdf, php-qrcode and what they need, under EventsCertificate\Vendor (bin/deps.php)
#   assets/third-party/      jsPDF, JSZip and SheetJS, with their licenses (bin/third-party-js.mjs)
#   build/, build-signer/, build-blocks/, assets/css, assets/js   the editor and the stylesheets
#
# docs/decisions/0032.
set -euo pipefail

cd "$(dirname "$0")/.."

composer_flags=(--no-interaction --prefer-dist)
[ -n "${EVCERT_NO_DEV:-}" ] && composer_flags+=(--no-dev)
composer install "${composer_flags[@]}"
npm ci

read -r -a wp <<< "${WP:-wp}"
"${wp[@]}" --require=bin/deps.php evcert deps

npm run build
