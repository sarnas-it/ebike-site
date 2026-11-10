"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");
const picker = require(path.join(__dirname, "..", "assets", "js", "picker.js"));

const mainPath = path.join(__dirname, "..", "assets", "js", "main.js");
const mainSource = fs.readFileSync(mainPath, "utf8");
const products = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "..", "..", "..", "content", "products.json"), "utf8"));

const models = {};
const nameToKey = {};

products.forEach(function (product) {
	models[product.slug] = {
		slug: product.slug,
		name: product.name,
		price: new Intl.NumberFormat("ru-RU").format(product.price) + " ₽",
		price_value: product.price,
		href: "#model-" + product.slug,
		note: product.tagline || "",
		category: product.category
	};
	nameToKey[product.name] = product.slug;
});

function makeChip(q, v) {
	const chip = {
		dataset: { q: q, v: v },
		attributes: {},
		listeners: [],
		setAttribute: function (name, value) {
			this.attributes[name] = value;
		},
		addEventListener: function (type, handler) {
			if (type === "click") {
				this.listeners.push(handler);
			}
		},
		click: function () {
			this.listeners.forEach(function (handler) {
				handler({});
			});
		}
	};

	return chip;
}

const chips = [];
["city", "mountain", "family", "commute"].forEach(function (place) {
	chips.push(makeChip("place", place));
});
["range", "cargo", "compact", "speed"].forEach(function (need) {
	chips.push(makeChip("need", need));
});
["low", "mid", "high"].forEach(function (budget) {
	chips.push(makeChip("budget", budget));
});

const resultEl = { innerHTML: "" };
const resetBtn = {
	hidden: true,
	addEventListener: function () {}
};

const document = {
	getElementById: function (id) {
		if (id === "quiz-result") {
			return resultEl;
		}
		if (id === "quiz-reset") {
			return resetBtn;
		}
		return null;
	},
	querySelectorAll: function (selector) {
		if (selector === ".chip[data-q]") {
			return chips;
		}
		const grouped = selector.match(/^\.chip\[data-q="([^"]+)"\]$/);
		if (grouped) {
			return chips.filter(function (chip) {
				return chip.dataset.q === grouped[1];
			});
		}
		return [];
	},
	body: {
		addEventListener: function () {}
	}
};

const sandbox = {
	document: document,
	window: null,
	console: console,
	Intl: Intl,
	Number: Number,
	String: String
};
sandbox.window = sandbox;
sandbox.window.setTimeout = function (handler) {
	handler();
};
sandbox.window.velotokPicker = picker;
sandbox.window.velotokL10n = {
	models: models,
	emptyResult: "Ответьте на вопросы",
	noModel: "Нет модели в бюджете",
	resultKicker: "Ваш вариант",
	viewModel: "Смотреть модель"
};

vm.createContext(sandbox);
vm.runInContext(mainSource, sandbox);

function clickChip(q, v) {
	const chip = chips.filter(function (candidate) {
		return candidate.dataset.q === q && candidate.dataset.v === v;
	})[0];

	if (!chip) {
		throw new Error("нет чипа " + q + "/" + v);
	}

	chip.click();
}

const places = ["city", "mountain", "family", "commute"];
const needs = ["range", "cargo", "compact", "speed"];
const budgets = ["low", "mid", "high"];
const failures = [];
let combinations = 0;

places.forEach(function (place) {
	needs.forEach(function (need) {
		budgets.forEach(function (budget) {
			combinations++;
			clickChip("place", place);
			clickChip("need", need);
			clickChip("budget", budget);

			const name = (resultEl.innerHTML.match(/<h3>([^<]+)<\/h3>/) || [])[1] || "";
			const key = nameToKey[name];
			const model = key ? models[key] : null;

			if (!model) {
				failures.push(place + "/" + need + "/" + budget + ": виджет не показал модель (" + resultEl.innerHTML + ")");
				return;
			}

			if (!picker.matchesBudget(model.price_value, budget)) {
				failures.push(place + "/" + need + "/" + budget + ": показана " + name + " за " + model.price_value + " ₽");
			}
		});
	});
});

if (combinations !== 48) {
	failures.push("ожидалось 48 комбинаций, получено " + combinations);
}

clickChip("place", "city");
clickChip("need", "range");
clickChip("budget", "low");
if (resultEl.innerHTML.indexOf("Курьер") !== -1 || resultEl.innerHTML.indexOf("Хребет Trail") !== -1) {
	failures.push("в бюджете «до 80 000» виджет показал модель дороже бюджета");
}

clickChip("budget", "high");
if (resultEl.innerHTML.indexOf("Стрит 7") !== -1) {
	failures.push("в бюджете «от 120 000» виджет показал Стрит 7 за 74 900 ₽");
}

if (failures.length) {
	failures.forEach(function (failure) {
		process.stderr.write("FAIL: " + failure + "\n");
	});
	process.exit(1);
}

console.log("ok - виджет main.js: все " + combinations + " комбинаций показывают модель в рамках бюджета");
console.log("ok - виджет main.js: «до 80 000» не показывает Курьер и Хребет Trail");
console.log("ok - виджет main.js: «от 120 000» не показывает Стрит 7");
console.log("PASS: виджет подбора использует бюджет как фильтр и цены товаров.");
