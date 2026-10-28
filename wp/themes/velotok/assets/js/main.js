(function () {
	"use strict";

	var l10n = window.velotokL10n || {};
	var menuOpenLabel = l10n.menuOpen || "Open menu";
	var menuCloseLabel = l10n.menuClose || "Close menu";

	var live = document.getElementById("live");
	var announce = function (text) {
		if (!live) {
			return;
		}
		live.textContent = "";
		window.setTimeout(function () {
			live.textContent = text;
		}, 40);
	};

	var menuBtn = document.getElementById("menu-toggle");
	var nav = document.getElementById("main-nav");
	if (menuBtn && nav) {
		menuBtn.addEventListener("click", function () {
			var open = menuBtn.getAttribute("aria-expanded") === "true";
			menuBtn.setAttribute("aria-expanded", String(!open));
			menuBtn.setAttribute("aria-label", open ? menuOpenLabel : menuCloseLabel);
			nav.classList.toggle("is-open", !open);
		});
		nav.querySelectorAll("a").forEach(function (link) {
			link.addEventListener("click", function () {
				menuBtn.setAttribute("aria-expanded", "false");
				menuBtn.setAttribute("aria-label", menuOpenLabel);
				nav.classList.remove("is-open");
			});
		});
		window.addEventListener("resize", function () {
			if (window.innerWidth > 980) {
				menuBtn.setAttribute("aria-expanded", "false");
				nav.classList.remove("is-open");
			}
		});
	}

	var models = l10n.models || {};
	var answers = {};
	var resultEl = document.getElementById("quiz-result");
	var resetBtn = document.getElementById("quiz-reset");
	var arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, function (character) {
			return {
				"&": "&amp;",
				"<": "&lt;",
				">": "&gt;",
				'"': "&quot;",
				"'": "&#039;"
			}[character];
		});
	}

	function pickModel() {
		var place = answers.place;
		var need = answers.need;
		var budget = answers.budget;
		if (place === "mountain") {
			return budget === "high" ? "hrebet-pro" : "hrebet-trail";
		}
		if (place === "family") {
			return budget === "low" ? "sklad" : "family";
		}
		if (need === "cargo") {
			return "courier";
		}
		if (need === "compact") {
			return "sklad";
		}
		if (place === "commute") {
			return budget === "low" ? "riviera" : "street";
		}
		if (budget === "high" || need === "speed") {
			return "street";
		}
		return "riviera";
	}

	function renderResult() {
		if (!resultEl) {
			return;
		}
		if (!answers.place || !answers.need || !answers.budget) {
			resultEl.innerHTML = '<p class="result-empty">' + escapeHtml(l10n.emptyResult || "") + '</p>';
			if (resetBtn) {
				resetBtn.hidden = true;
			}
			return;
		}
		var model = models[pickModel()];
		if (!model) {
			return;
		}
		var href = model.href || "#models";
		if (href.charAt(0) === "#" && !document.getElementById(href.slice(1))) {
			href = "#models";
		}
		resultEl.innerHTML = '<p class="result-kicker">' + escapeHtml(l10n.resultKicker || "") + '</p><div class="result-row"><div><h3>' + escapeHtml(model.name) + '</h3><p class="result-price">' + escapeHtml(model.price) + '</p></div><a class="btn btn-dark" href="' + escapeHtml(href) + '">' + escapeHtml(l10n.viewModel || "") + ' ' + arrow + '</a></div><p class="result-note">' + escapeHtml(model.note) + '</p>';
		if (resetBtn) {
			resetBtn.hidden = false;
		}
	}

	document.querySelectorAll(".chip[data-q]").forEach(function (chip) {
		chip.addEventListener("click", function () {
			var q = chip.dataset.q;
			answers[q] = chip.dataset.v;
			document.querySelectorAll('.chip[data-q="' + q + '"]').forEach(function (other) {
				other.setAttribute("aria-pressed", String(other === chip));
			});
			renderResult();
		});
	});

	if (resetBtn) {
		resetBtn.addEventListener("click", function () {
			answers = {};
			document.querySelectorAll(".chip[data-q]").forEach(function (chip) {
				chip.setAttribute("aria-pressed", "false");
			});
			renderResult();
		});
	}

	document.querySelectorAll(".velotok-ordering select").forEach(function (select) {
		select.addEventListener("change", function () {
			if (select.form) {
				select.form.submit();
			}
		});
	});

	document.body.addEventListener("added_to_cart", function () {
		announce(l10n.addedToCart || "");
	});

	document.body.addEventListener("removed_from_cart", function () {
		announce(l10n.removedFromCart || "");
	});
})();
