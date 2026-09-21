/**
 * wenpai-admin-ui kit behaviour. No jQuery. Product pages may no-op
 * by using data-wenpai-post on toggles (those submit via admin-post).
 */
(function () {
	"use strict";

	function inApp(el) {
		return el && el.closest && el.closest(".wenpai-app");
	}

	function reduceMotion() {
		return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}

	function setToggle(btn, on) {
		btn.classList.toggle("on", on);
		btn.setAttribute("aria-pressed", on ? "true" : "false");
		var label = on ? btn.getAttribute("data-on") : btn.getAttribute("data-off");
		if (!label) {
			return;
		}
		var knob = btn.querySelector("i");
		btn.textContent = "";
		if (knob) {
			btn.appendChild(knob);
		} else {
			var i = document.createElement("i");
			btn.appendChild(i);
		}
		btn.appendChild(document.createTextNode(" " + label));
	}

	function syncChoice(radio) {
		var name = radio.getAttribute("name");
		if (!name) {
			return;
		}
		var root = radio.form || radio.closest(".wenpai-app") || document;
		var nodes = root.querySelectorAll(".choice input[type=\"radio\"]");
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].name !== name) {
				continue;
			}
			var lab = nodes[i].closest(".choice");
			if (lab) {
				lab.classList.toggle("is-on", nodes[i].checked);
			}
		}
	}

	function syncGate(gate) {
		var box = gate.querySelector('input[type="checkbox"]');
		if (!box) {
			return;
		}
		var host = gate.closest("form") || gate.closest(".card") || gate.parentElement;
		if (!host) {
			return;
		}
		var apply = host.querySelectorAll("[data-wenpai-need-apply]");
		for (var i = 0; i < apply.length; i++) {
			apply[i].disabled = !box.checked;
		}
	}

	function boot(root) {
		var app = root || document;
		var radios = app.querySelectorAll(".wenpai-app .choice input[type=\"radio\"]");
		for (var i = 0; i < radios.length; i++) {
			syncChoice(radios[i]);
		}
		var gates = app.querySelectorAll(".wenpai-app .wenpai-gate");
		for (var g = 0; g < gates.length; g++) {
			syncGate(gates[g]);
		}
		if (reduceMotion()) {
			return;
		}
		var wraps = app.querySelectorAll(".wenpai-app .wenpai-notice-wrap[data-wenpai-fade]");
		for (var w = 0; w < wraps.length; w++) {
			(function (el) {
				window.setTimeout(function () {
					el.classList.add("is-gone");
				}, 3200);
			})(wraps[w]);
		}
	}

	document.addEventListener("click", function (e) {
		var btn = e.target.closest && e.target.closest(".wenpai-app button.toggle");
		if (!btn) {
			return;
		}
		if (btn.disabled || btn.classList.contains("is-locked") || btn.closest(".is-static")) {
			return;
		}
		if (btn.hasAttribute("data-wenpai-post") || btn.type === "submit") {
			return;
		}
		e.preventDefault();
		setToggle(btn, !btn.classList.contains("on"));
	});

	document.addEventListener("change", function (e) {
		var t = e.target;
		if (!inApp(t)) {
			return;
		}
		if (t.matches(".choice input[type=\"radio\"]")) {
			syncChoice(t);
		}
		if (t.matches(".wenpai-gate input[type=\"checkbox\"]")) {
			var gate = t.closest(".wenpai-gate");
			if (gate) {
				syncGate(gate);
			}
		}
	});

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			boot(document);
		});
	} else {
		boot(document);
	}
})();
