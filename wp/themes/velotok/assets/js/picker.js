(function (root, factory) {
	"use strict";

	if (typeof module === "object" && module.exports) {
		module.exports = factory();
		return;
	}

	root.velotokPicker = factory();
}(typeof globalThis !== "undefined" ? globalThis : this, function () {
	"use strict";

	var BUDGETS = {
		low: { min: null, max: 80000 },
		mid: { min: 80000, max: 120000 },
		high: { min: 120000, max: null }
	};

	function toNumber(value) {
		if (typeof value === "number") {
			return value;
		}

		if (value === null || typeof value === "undefined") {
			return NaN;
		}

		var text = String(value)
			.replace(/<[^>]*>/g, " ")
			.replace(/[\s\u00a0\u202f]/g, "")
			.replace(/[^\d.,-]/g, "")
			.replace(",", ".");

		if ("" === text) {
			return NaN;
		}

		return Number(text);
	}

	function priceOf(model) {
		if (!model || typeof model !== "object") {
			return NaN;
		}

		if (typeof model.price_value !== "undefined" && model.price_value !== null && model.price_value !== "") {
			return toNumber(model.price_value);
		}

		return toNumber(model.price);
	}

	function matchesBudget(price, budget) {
		var rule = BUDGETS[budget];

		if (!rule || !isFinite(price)) {
			return true;
		}

		if (rule.min !== null && price < rule.min) {
			return false;
		}

		if (rule.max !== null && price > rule.max) {
			return false;
		}

		return true;
	}

	function kindOf(model) {
		var slug = String((model && (model.slug || model.key)) || "").toLowerCase();
		var name = String((model && model.name) || "").toLowerCase();
		var haystack = slug + " " + name;

		if (haystack.indexOf("hrebet-pro") !== -1 || haystack.indexOf("хребет pro") !== -1) {
			return "mountain-pro";
		}
		if (haystack.indexOf("hrebet-trail") !== -1 || haystack.indexOf("хребет trail") !== -1) {
			return "mountain-trail";
		}
		if (haystack.indexOf("street") !== -1 || haystack.indexOf("стрит") !== -1) {
			return "city-sport";
		}
		if (haystack.indexOf("riviera") !== -1 || haystack.indexOf("ривьера") !== -1) {
			return "city-classic";
		}
		if (haystack.indexOf("family") !== -1 || haystack.indexOf("семейный") !== -1) {
			return "cargo-family";
		}
		if (haystack.indexOf("courier") !== -1 || haystack.indexOf("курьер") !== -1) {
			return "cargo-courier";
		}
		if (haystack.indexOf("sklad") !== -1 || haystack.indexOf("складень") !== -1) {
			return "folding";
		}

		return String((model && (model.category || model.category_slug)) || "").toLowerCase();
	}

	function categoryOf(model) {
		var category = String((model && (model.category || model.category_slug)) || "").toLowerCase();

		if ("" !== category) {
			return category;
		}

		var kind = kindOf(model);

		if ("folding" === kind) {
			return "folding";
		}
		if (kind.indexOf("mountain") === 0) {
			return "mountain";
		}
		if (kind.indexOf("cargo") === 0) {
			return "cargo";
		}
		if (kind.indexOf("city") === 0) {
			return "city";
		}

		return "";
	}

	function categoryPreferences(answers) {
		var place = answers.place || "";
		var need = answers.need || "";
		var order = [];

		function add(category) {
			if (category && order.indexOf(category) === -1) {
				order.push(category);
			}
		}

		if (place === "mountain") {
			add("mountain");
		} else if (place === "family") {
			add("cargo");
			add("folding");
		}

		if (need === "cargo") {
			add("cargo");
		} else if (need === "compact") {
			add("folding");
		}

		if (place === "commute" || place === "city") {
			add("city");
		}

		if (need === "speed") {
			add("city");
		} else if (need === "range") {
			add("mountain");
			add("cargo");
		}

		["city", "mountain", "cargo", "folding"].forEach(add);

		return order;
	}

	function kindPreferences(answers) {
		var place = answers.place || "";
		var need = answers.need || "";
		var order = [];

		function add(kind) {
			if (kind && order.indexOf(kind) === -1) {
				order.push(kind);
			}
		}

		if (place === "mountain") {
			add("mountain-pro");
			add("mountain-trail");
		} else if (place === "family") {
			add("cargo-family");
			add("cargo-courier");
		}

		if (need === "cargo") {
			if (place === "family") {
				add("cargo-family");
				add("cargo-courier");
			} else {
				add("cargo-courier");
				add("cargo-family");
			}
		} else if (need === "compact") {
			add("folding");
		} else if (need === "range") {
			add("mountain-pro");
			add("mountain-trail");
			add("cargo-courier");
			add("cargo-family");
		}

		if (need === "speed") {
			add("city-sport");
		}

		if (place === "commute" || place === "city") {
			add("city-classic");
			add("city-sport");
		}

		[
			"city-classic",
			"city-sport",
			"mountain-trail",
			"mountain-pro",
			"cargo-courier",
			"cargo-family",
			"folding",
			"city",
			"mountain",
			"cargo"
		].forEach(add);

		return order;
	}

	function toEntries(models) {
		var entries = [];

		if (!models || typeof models !== "object") {
			return entries;
		}

		if (Array.isArray(models)) {
			models.forEach(function (model, index) {
				if (model && typeof model === "object") {
					entries.push({
						key: String(model.slug || model.key || index),
						model: model
					});
				}
			});
			return entries;
		}

		Object.keys(models).forEach(function (key) {
			var model = models[key];

			if (model && typeof model === "object") {
				entries.push({ key: key, model: model });
			}
		});

		return entries;
	}

	function pickModel(models, answers) {
		answers = answers || {};

		var categories = categoryPreferences(answers);
		var kinds = kindPreferences(answers);
		var best = null;
		var bestCategory = Infinity;
		var bestKind = Infinity;

		toEntries(models).forEach(function (entry) {
			if (!matchesBudget(priceOf(entry.model), answers.budget)) {
				return;
			}

			var category = categoryOf(entry.model);
			var kind = kindOf(entry.model);
			var categoryRank = categories.indexOf(category);
			var kindRank = kinds.indexOf(kind);

			if (categoryRank === -1) {
				categoryRank = categories.length;
			}
			if (kindRank === -1) {
				kindRank = kinds.length;
			}

			if (!best || categoryRank < bestCategory || (categoryRank === bestCategory && kindRank < bestKind)) {
				best = entry.key;
				bestCategory = categoryRank;
				bestKind = kindRank;
			}
		});

		return best;
	}

	return {
		BUDGETS: BUDGETS,
		priceOf: priceOf,
		matchesBudget: matchesBudget,
		pickModel: pickModel
	};
}));
