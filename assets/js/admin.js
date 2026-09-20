(function () {
  "use strict";

  var app = document.querySelector(".wpcy-app");
  if (!app) {
    return;
  }
  app.classList.add("is-ready");

  function closest(el, sel) {
    return el.closest ? el.closest(sel) : null;
  }

  function setToggleText(btn, on) {
    var knob = btn.querySelector("i");
    var onText = btn.getAttribute("data-on") || "已开启";
    var offText = btn.getAttribute("data-off") || "未开启";
    btn.textContent = "";
    if (knob) {
      btn.appendChild(knob);
    } else {
      knob = document.createElement("i");
      btn.appendChild(knob);
    }
    btn.appendChild(document.createTextNode(on ? onText : offText));
    var row = closest(btn, ".simple-row");
    if (row) {
      var tile = row.querySelector(".tile");
      if (tile) {
        tile.classList.toggle("ok", on);
      }
    }
  }

  function syncChoice(input) {
    var name = input.getAttribute("name");
    if (!name) {
      return;
    }
    app.querySelectorAll('.choice input[name="' + name + '"]').forEach(function (radio) {
      var label = closest(radio, ".choice");
      if (label) {
        label.classList.toggle("is-on", radio.checked);
        var tile = label.querySelector(".vendor-tile");
        if (tile) {
          tile.classList.toggle("accent", radio.checked);
        }
      }
    });
  }

  function postForm(form) {
    if (!form || form.classList.contains("is-busy")) {
      return;
    }
    form.classList.add("is-busy");
    form.querySelectorAll("button").forEach(function (btn) {
      btn.disabled = true;
    });
    HTMLFormElement.prototype.submit.call(form);
  }

  function field(form, key) {
    return form.querySelector('[name$="[' + key + ']"]');
  }

  function stashDraft(form) {
    if (!form) {
      return;
    }
    var data = {};
    form.querySelectorAll("input, select").forEach(function (el) {
      if (!el.name || el.type === "file") {
        return;
      }
      if (el.name.indexOf("[access_key_secret]") !== -1) {
        return;
      }
      if (el.type === "radio" && !el.checked) {
        return;
      }
      if (el.type === "checkbox") {
        data[el.name] = el.checked ? el.value : "";
        return;
      }
      data[el.name] = el.value;
    });
    try {
      sessionStorage.setItem("wpsss-draft", JSON.stringify(data));
    } catch (err) {}
  }

  function restoreDraft(form) {
    if (!form || form.getAttribute("data-wpsss-restored")) {
      return;
    }
    var raw;
    try {
      raw = sessionStorage.getItem("wpsss-draft");
    } catch (err) {
      return;
    }
    if (!raw) {
      return;
    }
    var data;
    try {
      data = JSON.parse(raw);
    } catch (err) {
      return;
    }
    Object.keys(data).forEach(function (name) {
      if (name.indexOf("[driver]") !== -1 || name.indexOf("[site_mode]") !== -1) {
        return;
      }
      var el = form.querySelector('[name="' + name + '"]');
      if (!el) {
        return;
      }
      var ctl = closest(el, ".field-ctl");
      var flip = ctl ? ctl.querySelector(".js-wpsss-flip") : null;
      if (flip && el.type === "hidden") {
        el.value = data[name];
        var on = data[name] === "1";
        flip.classList.toggle("on", on);
        setToggleText(flip, on);
        return;
      }
      if (el.type === "checkbox") {
        el.checked = data[name] === el.value;
        return;
      }
      if (el.tagName === "SELECT") {
        setSelectValue(el, data[name]);
        return;
      }
      el.value = data[name];
    });
    form.setAttribute("data-wpsss-restored", "1");
  }

  function setSelectValue(select, value) {
    if (!select || value == null || value === "") {
      return;
    }
    var found = false;
    Array.prototype.forEach.call(select.options, function (opt) {
      if (opt.value === value) {
        found = true;
      }
    });
    if (!found) {
      var opt = document.createElement("option");
      opt.value = value;
      opt.textContent = value;
      select.appendChild(opt);
    }
    select.value = value;
  }

  function syncTtl(form) {
    if (!form) {
      return;
    }
    var access = form.querySelector(".js-wpsss-access");
    var row = form.querySelector(".js-wpsss-ttl");
    if (access && row) {
      row.hidden = access.value !== "signed";
    }
  }

  function syncProtectHint(form) {
    if (!form) {
      return;
    }
    var style = form.querySelector(".js-wpsss-style");
    var hint = form.querySelector(".js-wpsss-protect-hint");
    var toggle = form.querySelector(".js-wpsss-protect-toggle");
    var hasStyle = !!(style && style.value.trim() !== "");
    if (hint) {
      hint.hidden = hasStyle;
    }
    if (toggle) {
      var hidden = toggle.parentNode ? toggle.parentNode.querySelector('input[type="hidden"]') : null;
      var on = hidden ? hidden.value === "1" : toggle.classList.contains("on");
      toggle.disabled = !hasStyle && !on;
    }
  }

  function syncOssExtras(form) {
    if (!form) {
      return;
    }
    var region = form.querySelector(".js-wpsss-region");
    var origin = form.querySelector(".js-wpsss-origin-region");
    if (region && origin) {
      origin.hidden = String(region.value || "").indexOf("accelerate") === -1;
    }
    var auth = form.querySelector(".js-wpsss-oss-auth");
    var role = form.querySelector(".js-wpsss-ram-role");
    if (auth && role) {
      role.hidden = auth.value !== "ram";
    }
  }

  function fillCosAppId(form) {
    if (!form) {
      return;
    }
    var bucket = form.querySelector(".js-wpsss-bucket");
    var app = form.querySelector(".js-wpsss-app-id");
    if (!bucket || !app || app.value) {
      return;
    }
    var match = bucket.value.trim().match(/-(\d{8,})$/);
    if (match) {
      app.value = match[1];
    }
  }

  function inspectHint(form, text, ok) {
    var hint = form.querySelector(".js-wpsss-inspect-hint");
    if (!hint) {
      return;
    }
    hint.hidden = !text;
    hint.textContent = text || "";
    hint.classList.toggle("is-ok", !!ok);
    hint.classList.toggle("is-bad", !ok && !!text);
  }

  function previewTo(key, value) {
    stashDraft(document.getElementById("wpsss-settings-form"));
    var url = new URL(window.location.href);
    url.searchParams.set("preview_" + key, value);
    url.hash = "wpsss-fields";
    window.location.assign(url.toString());
  }

  app.querySelectorAll(".choice").forEach(function (label) {
    var input = label.querySelector('input[type="radio"]');
    if (!input) {
      return;
    }
    label.addEventListener("click", function (event) {
      if (input.disabled) {
        var go = label.getAttribute("data-wpsss-goto");
        if (go) {
          event.preventDefault();
          window.location.assign(go);
        }
        return;
      }
      var already = input.checked;
      if (event.target !== input) {
        event.preventDefault();
        if (already) {
          return;
        }
        input.checked = true;
      } else if (already) {
        return;
      }
      syncChoice(input);
      if (input.getAttribute("data-wpsss-preview")) {
        previewTo(input.getAttribute("data-wpsss-preview"), input.value);
        return;
      }
      var form = closest(input, "form.js-wpsss-instant");
      if (form) {
        postForm(form);
      }
    });
  });

  app.querySelectorAll(".js-wpsss-toggle").forEach(function (btn) {
    btn.addEventListener("click", function (event) {
      event.preventDefault();
      if (btn.disabled) {
        return;
      }
      var form = closest(btn, "form");
      if (!form) {
        return;
      }
      var on = !btn.classList.contains("on");
      btn.classList.toggle("on", on);
      setToggleText(btn, on);
      postForm(form);
    });
  });

  app.querySelectorAll(".js-wpsss-flip").forEach(function (btn) {
    btn.addEventListener("click", function (event) {
      event.preventDefault();
      if (btn.disabled) {
        return;
      }
      var ctl = closest(btn, ".field-ctl");
      var hidden = ctl ? ctl.querySelector('input[type="hidden"]') : null;
      if (!hidden) {
        return;
      }
      var on = hidden.value !== "1";
      hidden.value = on ? "1" : "0";
      btn.classList.toggle("on", on);
      setToggleText(btn, on);
    });
  });

  app.querySelectorAll("form[data-wpsss-need]").forEach(function (form) {
    var name = form.getAttribute("data-wpsss-need");
    var box = form.querySelector('input[name="' + name + '"]');
    var apply = form.querySelectorAll("[data-wpsss-need-apply]");
    if (!box || apply.length === 0) {
      return;
    }
    function update() {
      var ok = box.checked;
      form.classList.toggle("is-confirmed", ok);
      apply.forEach(function (btn) {
        btn.disabled = !ok;
      });
    }
    box.addEventListener("change", update);
    update();
  });

  app.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function () {
      form.classList.add("is-busy");
      form.querySelectorAll("button").forEach(function (btn) {
        btn.disabled = true;
      });
    });
  });

  var flash = app.querySelector(".wpsss-notice-wrap .notice.ok");
  if (flash) {
    window.setTimeout(function () {
      var wrap = closest(flash, ".wpsss-notice-wrap");
      if (wrap) {
        wrap.classList.add("is-gone");
      }
    }, 3200);
  }

  var setupForm = document.getElementById("wpsss-setup-step2");
  if (setupForm) {
    fillCosAppId(setupForm);
    setupForm.querySelectorAll("[data-wpsss-setup-driver]").forEach(function (input) {
      input.addEventListener("change", function () {
        var next = new URL(window.location.href);
        next.searchParams.set("preview_driver", input.value);
        window.location.assign(next.toString());
      });
    });
    var setupBucket = setupForm.querySelector(".js-wpsss-bucket");
    if (setupBucket) {
      setupBucket.addEventListener("blur", function () {
        fillCosAppId(setupForm);
      });
    }
  }

  var settingsForm = document.getElementById("wpsss-settings-form");
  if (settingsForm) {
    restoreDraft(settingsForm);
    syncTtl(settingsForm);
    syncOssExtras(settingsForm);
    syncProtectHint(settingsForm);
    fillCosAppId(settingsForm);
    var styleInput = settingsForm.querySelector(".js-wpsss-style");
    if (styleInput) {
      styleInput.addEventListener("input", function () {
        syncProtectHint(settingsForm);
      });
    }
    var access = settingsForm.querySelector(".js-wpsss-access");
    if (access) {
      access.addEventListener("change", function () {
        syncTtl(settingsForm);
      });
    }
    var region = settingsForm.querySelector(".js-wpsss-region");
    if (region) {
      region.addEventListener("change", function () {
        syncOssExtras(settingsForm);
      });
    }
    var ossAuth = settingsForm.querySelector(".js-wpsss-oss-auth");
    if (ossAuth) {
      ossAuth.addEventListener("change", function () {
        syncOssExtras(settingsForm);
      });
    }
    var bucket = settingsForm.querySelector(".js-wpsss-bucket");
    if (bucket) {
      bucket.addEventListener("blur", function () {
        fillCosAppId(settingsForm);
      });
    }
    var inspectBtn = settingsForm.querySelector(".js-wpsss-inspect");
    if (inspectBtn) {
      inspectBtn.addEventListener("click", function () {
        if (!window.wpsssAdmin || inspectBtn.disabled) {
          return;
        }
        fillCosAppId(settingsForm);
        inspectHint(settingsForm, "正在读取…", true);
        inspectBtn.disabled = true;
        var body = new URLSearchParams();
        body.set("action", "wpsss_inspect");
        body.set("nonce", wpsssAdmin.inspectNonce);
        var driver = settingsForm.querySelector('[name$="[driver]"]:checked');
        body.set("driver", driver ? driver.value : "");
        ["bucket", "region", "access_key_id", "access_key_secret", "app_id", "endpoint", "s3_preset", "force_path_style", "use_internal", "custom_domain"].forEach(function (key) {
          var el = field(settingsForm, key);
          if (el) {
            body.set(key, el.value);
          }
        });
        fetch(wpsssAdmin.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: body.toString(),
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (json) {
            inspectBtn.disabled = false;
            if (!json || !json.success) {
              inspectHint(settingsForm, (json && json.data && json.data.message) || "读取失败。", false);
              return;
            }
            var data = json.data || {};
            var region = field(settingsForm, "region");
            if (region && data.region) {
              setSelectValue(region, data.region);
              syncOssExtras(settingsForm);
            }
            var accessEl = settingsForm.querySelector(".js-wpsss-access");
            if (accessEl && data.url_access) {
              accessEl.value = data.url_access;
              syncTtl(settingsForm);
            }
            var app = settingsForm.querySelector(".js-wpsss-app-id");
            if (app && data.app_id && !app.value) {
              app.value = data.app_id;
            }
            var domain = field(settingsForm, "custom_domain");
            if (domain && data.custom_domain && !domain.value) {
              domain.value = data.custom_domain;
            }
            var notes = (data.notes || []).filter(Boolean);
            inspectHint(
              settingsForm,
              notes.length ? notes.join(" ") : "已填入建议值，请点击保存。",
              true
            );
          })
          .catch(function () {
            inspectBtn.disabled = false;
            inspectHint(settingsForm, "读取失败，请稍后重试。", false);
          });
      });
    }
  }
})();
