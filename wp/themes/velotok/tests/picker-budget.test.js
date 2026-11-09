"use strict";

const fs = require("fs");
const path = require("path");
const picker = require(path.join(__dirname, "..", "assets", "js", "picker.js"));

const dataPath = path.join(__dirname, "..", "..", "..", "data", "products.json");
const products = JSON.parse(fs.readFileSync(dataPath, "utf8"));
const models = {};

products.forEach(function (product) {
	models[product.slug] = {
		slug: product.slug,
		name: product.name,
		price: String(product.price).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₽",
		price_value: product.price,
		category: product.category
	};
});

const places = ["city", "mountain", "family", "commute"];
const needs = ["range", "cargo", "compact", "speed"];
const budgets = ["low", "mid", "high"];
const failures = [];
let combinations = 0;

places.forEach(function (place) {
	needs.forEach(function (need) {
		budgets.forEach(function (budget) {
			combinations++;
			const answers = { place: place, need: need, budget: budget };
			const key = picker.pickModel(models, answers);

			if (!key || !models[key]) {
				failures.push(place + "/" + need + "/" + budget + ": модель не найдена");
				return;
			}

			const price = picker.priceOf(models[key]);

			if (!picker.matchesBudget(price, budget)) {
				failures.push(
					place + "/" + need + "/" + budget + ": " + models[key].name + " за " + price + " ₽"
				);
			}
		});
	});
});

if (combinations !== 48) {
	failures.push("ожидалось 48 комбинаций, получено " + combinations);
}

if (failures.length) {
	failures.forEach(function (failure) {
		process.stderr.write("FAIL: " + failure + "\n");
	});
	process.exit(1);
}

console.log("ok - все " + combinations + " комбинаций подбора укладываются в бюджет");

if (!picker.matchesBudget(80000, "low") || picker.matchesBudget(80001, "low")) {
	process.stderr.write("FAIL: верхняя граница бюджета «до 80 000» должна быть включительной\n");
	process.exit(1);
}

if (!picker.matchesBudget(80000, "mid") || !picker.matchesBudget(120000, "mid")) {
	process.stderr.write("FAIL: границы среднего бюджета должны быть включительными\n");
	process.exit(1);
}

if (!picker.matchesBudget(120000, "high") || picker.matchesBudget(119999, "high")) {
	process.stderr.write("FAIL: нижняя граница бюджета «от 120 000» должна быть включительной\n");
	process.exit(1);
}

console.log("ok - границы бюджетов проверяются включительно");

const changed = Object.assign({}, models, {
	riviera: Object.assign({}, models.riviera, {
		price: "81 000 ₽",
		price_value: 81000
	})
});
const changedPick = picker.pickModel(changed, { place: "city", need: "range", budget: "low" });

if (changedPick === "riviera") {
	process.stderr.write("FAIL: подбор использует не цену товара, а захардкоженный выбор\n");
	process.exit(1);
}

if (picker.pickModel({ only: { slug: "only", price_value: 200000, category: "city" } }, { place: "city", need: "range", budget: "low" }) !== null) {
	process.stderr.write("FAIL: при отсутствии модели в бюджете подборщик должен вернуть пустой результат\n");
	process.exit(1);
}

const unknownPick = picker.pickModel({ unknown: { name: "Без категории", price: "50 000 ₽" } }, { place: "city", need: "range", budget: "low" });

if (unknownPick !== "unknown") {
	process.stderr.write("FAIL: модель без категории должна корректно обрабатываться\n");
	process.exit(1);
}

console.log("ok - цена берётся из данных модели, а не из жёсткой таблицы");
console.log("PASS: подборщик фильтрует модели по бюджету во всех комбинациях.");
