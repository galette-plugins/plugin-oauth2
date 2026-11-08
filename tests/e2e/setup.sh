#!/bin/bash

#
# This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
# SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
# SPDX-License-Identifier: GPL-3.0-or-later
#

set -euo pipefail

if [ -f config/private.key ]; then
    echo "config/private.key exists, keeping the current keys and configuration"
    exit 0
fi

openssl genrsa -out config/private.key 2048
openssl rsa -in config/private.key -pubout -out config/public.key
chmod 660 config/*.key

{
    echo '<?php'
    echo 'declare(strict_types=1);'
    echo "\$encryptionKey = '$(vendor/bin/generate-defuse-key)';"
} > config/encryption-key.php

cp tests/config/config.yml config/config.yml
