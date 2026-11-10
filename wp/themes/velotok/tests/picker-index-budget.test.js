"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const indexPath = path.join(__dirname, "..", "..", "..", "..", "index.html");
const html = fs.readFileSync(indexPath, "utf8");
const script = html.match(/<script>([\s\S]*?)<\/script>/);

if (!script) {
	process.stderr.write("FAIL: в index.html не найден скрипт подборщика\n");
	process.exit(1);
}

const cards = [];
const cardRe = /<article class="model-card" id="([^"]+)"([^>]*)>([\s\S]*?)<\/article>/g;
let match;

while ((match = cardRe.exec(html))) {
	const id = match[1];
	const attrs = match[2];
	const body = match[3];
	const category = (attrs.match(/data-category="([^"]*)"/) || [])[1] || "";
	const note = (attrs.match(/data-note="([^"]*)"/) || [])[1] || "";
	const name = (body.match(/<h3>([^<]*)<\/h3>/) || [])[1] || "";
	const price = Number((body.match(/data-price="(\d+)"/) || [])[1]);
	const priceText = (body.match(/class="price">([^<]*)/) || [])[1] || "";

	cards.push({
		id: id,
		dataset: { category: category, note: note },
		querySelector: function (selector) {
			if (selector === "h3") {
				return { textContent: name };
			}
			if (selector === ".price") {
				return { textContent: priceText };
			}
			if (selector === "[data-price]") {
				return { dataset: { price: String(price) } };
			}
			return null;
		}
	});
}

if (cards.length === 0) {
	process.stderr.write("FAIL: в index.html не найдены карточки товаров\n");
	process.exit(1);
}

const stubElement = {
	addEventListener: function () {},
	setAttribute: function () {},
	focus: function () {}
};

const sandbox = {
	document: {
		querySelectorAll: function (selector) {
			return selector === ".model-card" ? cards : [];
		},
		getElementById: function () {
			return stubElement;
		}
	},
	money: function (value) {
		return new Intl.NumberFormat("ru-RU").format(value) + " ₽";
	},
	Intl: Intl,
	Number: Number,
	String: String,
	Object: Object,
	Array: Array,
	Math: Math,
	isFinite: isFinite,
	Infinity: Infinity,
	NaN: NaN
};

const start = script[1].indexOf("  var BUDGETS =");
const end = script[1].indexOf("  function renderResult");

if (start < 0 || end < 0) {
	process.stderr.write("FAIL: в index.html не найден код фильтра бюджета\n");
	process.exit(1);
}

vm.createContext(sandbox);
vm.runInContext(script[1].slice(start, end), sandbox);

const places = ["city", "mountain", "family", "commute"];
const needs = ["range", "cargo", "compact", "speed"];
const budgets = ["low", "mid", "high"];
const failures = [];
let combinations = 0;

places.forEach(function (place) {
	needs.forEach(function (need) {
		budgets.forEach(function (budget) {
			combinations++;
			sandbox.answers = { place: place, need: need, budget: budget };
			const key = sandbox.pickModel();

			if (!key || !sandbox.models[key]) {
				failures.push(place + "/" + need + "/" + budget + ": модель не найдена");
				return;
			}

			const model = sandbox.models[key];
			const price = sandbox.priceOf(model);

			if (!sandbox.matchesBudget(price, budget)) {
				failures.push(
					place + "/" + need + "/" + budget + ": " + model.name + " за " + price + " ₽"
				);
			}
		});
	});
});

if (combinations !== 48) {
	failures.push("ожидалось 48 комбинаций, получено " + combinations);
}

if (!sandbox.matchesBudget(80000, "low") || sandbox.matchesBudget(80001, "low")) {
	failures.push("верхняя граница бюджета «до 80 000» должна быть включительной");
}

if (!sandbox.matchesBudget(80000, "mid") || !sandbox.matchesBudget(120000, "mid")) {
	failures.push("границы среднего бюджета должны быть включительными");
}

if (!sandbox.matchesBudget(120000, "high") || sandbox.matchesBudget(119999, "high")) {
	failures.push("нижняя граница бюджета «от 120 000» должна быть включительной");
}

if (sandbox.matchesBudget(NaN, "low") || sandbox.matchesBudget(Infinity, "mid") || sandbox.matchesBudget(undefined, "high")) {
	failures.push("неизвестная цена не должна считаться подходящей ни под один бюджет");
}

if (!script[1].includes("content/products.json") || script[1].indexOf("fetch(PRODUCT_DATA_URL") === -1) {
	failures.push("цены подборщика должны загружаться из каталога товаров content/products.json");
}

if (typeof sandbox.modelsFromProducts !== "function") {
	failures.push("в index.html не найдено преобразование товаров в модели подборщика");
} else {
	const productsPath = path.join(__dirname, "..", "..", "..", "..", "content", "products.json");
	const products = JSON.parse(fs.readFileSync(productsPath, "utf8"));
	const cardModels = sandbox.models;
	const productModels = sandbox.modelsFromProducts(products, cardModels);

	products.forEach(function (product) {
		const model = productModels[product.slug] || Object.keys(productModels).map(function (key) {
			return productModels[key];
		}).filter(function (candidate) {
			return candidate.name === product.name;
		})[0];

		if (!model) {
			failures.push("товар " + product.name + " не попал в модели подборщика");
			return;
		}

		if (model.price_value !== product.price) {
			failures.push("цена " + product.name + " взята не из каталога товаров");
		}
	});

	sandbox.models = productModels;

	places.forEach(function (place) {
		needs.forEach(function (need) {
			budgets.forEach(function (budget) {
				sandbox.answers = { place: place, need: need, budget: budget };
				const key = sandbox.pickModel();

				if (!key || !sandbox.models[key]) {
					failures.push("товарный каталог " + place + "/" + need + "/" + budget + ": модель не найдена");
					return;
				}

				const model = sandbox.models[key];
				const price = sandbox.priceOf(model);

				if (!sandbox.matchesBudget(price, budget)) {
					failures.push(
						"товарный каталог " + place + "/" + need + "/" + budget + ": " + model.name + " за " + price + " ₽"
					);
				}
			});
		});
	});

	sandbox.models = cardModels;
}

if (failures.length) {
	failures.forEach(function (failure) {
		process.stderr.write("FAIL: " + failure + "\n");
	});
	process.exit(1);
}

console.log("ok - index.html: все " + combinations + " комбинаций укладываются в бюджет");
console.log("ok - index.html: границы бюджетов проверяются включительно");
console.log("ok - index.html: модель без цены не обходит фильтр бюджета");
console.log("ok - index.html: цены берутся из каталога товаров content/products.json");
console.log("PASS: подборщик на главной странице фильтрует модели по бюджету.");
