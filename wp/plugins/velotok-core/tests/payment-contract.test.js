#!/usr/bin/env node
'use strict';

const path = require('path');
const { spawnSync } = require('child_process');

const testPath = path.join(__dirname, 'payment-gateway-contract.test.js');
const result = spawnSync(process.execPath, [testPath], { stdio: 'inherit' });

process.exit(result.status === null ? 1 : result.status);
