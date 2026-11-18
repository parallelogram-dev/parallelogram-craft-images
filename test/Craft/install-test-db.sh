#!/usr/bin/env bash
# Installs Craft into the craft_images_test database from the sandbox's
# project config (which includes this plugin and the test volume and
# filesystems). Run from the sandbox project. The admin account is never used,
# so its password is random and not kept.
set -euo pipefail

export CRAFT_DB_DATABASE="${CRAFT_TEST_DB_DATABASE:-craft_images_test}"

if php craft install/check | grep -q "Craft is installed"; then
    echo "${CRAFT_DB_DATABASE} is already installed."
    exit 0
fi

php craft install/craft \
    --interactive=0 \
    --username=tester \
    --email=tester@example.test \
    --password="$(openssl rand -hex 24)" \
    --site-name="Parallelogram Images tests" \
    --site-url="http://localhost" \
    --language=en
