#!/usr/bin/env node
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const wpRoot = path.resolve(__dirname, '..');
const themesRoot = path.join(wpRoot, 'themes');
const manifestPath = path.join(themesRoot, 'design-variants.json');
const helperPath = path.join(wpRoot, 'cli', 'theme-variants.php');
const phpTestPath = path.join(__dirname, 'theme-variants-test.php');

assert.ok(fs.existsSync(manifestPath), `Theme variants manifest is missing: ${manifestPath}`);
assert.ok(fs.existsSync(helperPath), `Theme variants selector is missing: ${helperPath}`);

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const helper = fs.readFileSync(helperPath, 'utf8');

assert.strictEqual(manifest.spec, 'clarify-c1924ac5', 'Manifest must reference spec clarify-c1924ac5');
assert.ok(Array.isArray(manifest.variants), 'Manifest must contain a variants array');
assert.ok(manifest.variants.length >= 2, 'Manifest must offer at least two design variants');

const requiredFiles = [
  'style.css',
  'index.php',
  'functions.php',
  'front-page.php',
  'header.php',
  'footer.php',
  'single.php',
  'page.php',
  '404.php',
  'inc/enqueue.php',
  'inc/setup.php',
  'inc/woocommerce.php',
  'template-parts/hero.php',
  'template-parts/models.php',
  'assets/css/main.css',
  'assets/js/main.js',
  'assets/img/hero-city-1-web.jpg',
];

const names = new Map();
const slugs = new Set();
let selectedCount = 0;
let rejectedCount = 0;

for (const variant of manifest.variants) {
  const { slug, name, status } = variant;
  assert.ok(slug, 'Every variant must have a slug');
  assert.ok(/^[a-z0-9-]+$/.test(slug), `Invalid variant slug: ${slug}`);
  assert.ok(!slugs.has(slug), `Duplicate variant slug: ${slug}`);
  assert.ok(name, `Variant ${slug} must have a name`);
  assert.ok(['selected', 'rejected'].includes(status), `Variant ${slug} has invalid status: ${status}`);
  slugs.add(slug);

  const dir = path.join(themesRoot, slug);
  assert.ok(fs.statSync(dir).isDirectory(), `Variant directory is missing: ${dir}`);

  for (const file of requiredFiles) {
    assert.ok(fs.existsSync(path.join(dir, file)), `Variant ${slug} is missing ${file}`);
  }

  const style = fs.readFileSync(path.join(dir, 'style.css'), 'utf8');
  const match = style.match(/^\s*Theme Name:\s*(.+)$/mi);
  assert.ok(match, `Variant ${slug} has no Theme Name header`);
  const themeName = match[1].trim();
  assert.ok(!names.has(themeName), `Duplicate Theme Name: ${themeName}`);
  names.set(themeName, slug);

  if (status === 'selected') {
    selectedCount += 1;
    assert.strictEqual(manifest.selected, slug, `Selected variant does not match manifest.selected (${slug})`);
  } else {
    rejectedCount += 1;
  }
}

assert.strictEqual(selectedCount, 1, 'Exactly one design variant must be selected');
assert.strictEqual(rejectedCount, manifest.variants.length - 1, 'All unselected variants must be marked rejected');

assert.ok(helper.includes('velotok_theme_variants_select'), 'Selection helper is missing');
assert.ok(helper.includes('velotok_theme_variants_validate'), 'Manifest validation helper is missing');
assert.ok(helper.includes("'rejected'"), 'Selection helper must mark other variants as rejected');
assert.ok(helper.includes("'selected'"), 'Selection helper must mark the chosen variant as selected');
assert.ok(
  helper.includes("$manifest['selected'] = $slug"),
  'Selection helper must persist the selected variant in the manifest'
);

const composePath = path.resolve(wpRoot, '..', 'docker-compose.yml');
const entrypointPath = path.join(wpRoot, 'cli', 'wp-entrypoint.sh');
const bootstrapPath = path.join(wpRoot, 'cli', 'bootstrap.sh');
const compose = fs.readFileSync(composePath, 'utf8');
const entrypoint = fs.readFileSync(entrypointPath, 'utf8');
const isExecutable = (file) => (fs.statSync(file).mode & 0o111) !== 0;

assert.ok(
  /entrypoint:\s*\["\/bin\/sh",\s*"\/opt\/velotok\/cli\/wp-entrypoint\.sh"\]/.test(compose),
  'wpcli entrypoint must invoke the script through /bin/sh so it runs even without the executable bit'
);
assert.ok(
  entrypoint.includes('/bin/sh /opt/velotok/cli/bootstrap.sh'),
  'wp-entrypoint.sh must run bootstrap.sh through /bin/sh instead of executing it directly'
);
assert.ok(isExecutable(entrypointPath), 'wp-entrypoint.sh must be executable');
assert.ok(isExecutable(bootstrapPath), 'bootstrap.sh must be executable');

const php = spawnSync('php', ['-v'], { encoding: 'utf8' });

if (php.status === 0) {
  const result = spawnSync('php', [phpTestPath], { encoding: 'utf8' });

  if (result.stdout) {
    process.stdout.write(result.stdout);
  }
  if (result.stderr) {
    process.stderr.write(result.stderr);
  }

  assert.strictEqual(result.status, 0, 'PHP theme variants contract test failed');
  console.log('PASS: theme variants verified with PHP');
} else {
  console.log('PASS: theme variants, complete files and selected/rejected statuses verified (PHP not available)');
}
