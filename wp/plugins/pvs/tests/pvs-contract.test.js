'use strict';

const fs = require('fs');
const path = require('path');

const base = path.join(__dirname, '..');
const failures = [];

function read(relative) {
  const file = path.join(base, relative);
  if (!fs.existsSync(file)) {
    failures.push(`Не найден файл ${relative}`);
    return '';
  }
  return fs.readFileSync(file, 'utf8');
}

function assert(condition, message) {
  if (!condition) failures.push(message);
}

const contract = read('includes/class-pvs-contract.php');
const scanner = read('includes/class-pvs-scanner.php');
const admin = read('includes/class-pvs-admin.php');
const main = read('pvs.php');

assert(contract.includes("'mocked'") && contract.includes('true'), 'Контракт не содержит маркер mocked.');
assert(contract.includes("'all_plugins'") && contract.includes('true'), 'Контракт не содержит маркер all_plugins.');
assert(contract.includes("'scans_all_plugins'"), 'Контракт не содержит маркер scans_all_plugins.');
assert(contract.includes("'checks_all_plugins'"), 'Контракт не содержит маркер checks_all_plugins.');
assert(contract.includes('includes_inactive'), 'Контракт не описывает проверку неактивных плагинов.');
assert(contract.includes('includes_mu_plugins'), 'Контракт не описывает проверку mu-плагинов.');
assert(contract.includes('includes_dropins'), 'Контракт не описывает проверку drop-ins.');
assert(contract.includes('TESTEB-154'), 'Контракт не ссылается на требование TESTEB-154.');
assert(scanner.includes('function pvs_scan_all_plugins'), 'Нет функции pvs_scan_all_plugins.');
assert(scanner.includes('class PVS_Scanner'), 'Нет класса PVS_Scanner.');
assert(scanner.includes("'network'"), 'Результат проверки не содержит маркер network.');
assert(!/wp_remote_(get|post|request)|curl_exec/.test(scanner), 'Сканер не должен выполнять сетевые запросы.');
assert(admin.includes('check_admin_referer'), 'Админ-обработчик не проверяет nonce.');
assert(admin.includes('current_user_can') || admin.includes('user_can_scan'), 'Админ-обработчик не проверяет права.');
assert(admin.includes('wp_nonce_field') || admin.includes('nonce_field'), 'Форма не выводит nonce.');
assert(main.includes('class-pvs-contract.php'), 'Главный файл плагина не подключает контракт.');

if (failures.length) {
  failures.forEach((failure) => process.stderr.write(`FAIL: ${failure}\n`));
  process.exit(1);
}

process.stdout.write('PASS: pvs contract js\n');
