(function () {
  "use strict";

  var cfg = window.wpsssAdmin || {};
  var copy = cfg.copy || {};

  function askConfirm(msg) {
    return !msg || window.confirm(msg);
  }

  document.addEventListener(
    "click",
    function (event) {
      var el = event.target && event.target.closest ? event.target.closest("[data-wpsss-confirm]") : null;
      if (!el || el.disabled) {
        return;
      }
      if (el.tagName === "BUTTON" && el.form) {
        return;
      }
      if (!askConfirm(el.getAttribute("data-wpsss-confirm"))) {
        event.preventDefault();
        event.stopPropagation();
      }
    },
    true
  );

  var filter = document.getElementById("posts-filter");
  if (filter) {
    filter.addEventListener(
      "submit",
      function (event) {
        var action = filter.querySelector('select[name="action"]');
        var value = action ? action.value : "";
        if (value === "wpsss_pull" && !askConfirm(copy.confirmBulkPull || "确认批量拉回所选附件？本地已有同名文件时会先改名保留。")) {
          event.preventDefault();
          return;
        }
        if ((value === "delete" || value === "delete_all") && filter.querySelector("[data-wpsss-offloaded]")) {
          if (!askConfirm(copy.confirmBulkDelete || "所选附件若已进桶，永久删除会同时删掉桶内对象，没有回收站。先确认本地还有副本。仍要删除吗？")) {
            event.preventDefault();
          }
        }
      },
      true
    );
    filter.querySelectorAll("tr").forEach(function (tr) {
      if (!tr.querySelector("[data-wpsss-offloaded], a[href*='wpsss_pull_one']")) {
        return;
      }
      tr.querySelectorAll("a.submitdelete, .delete a").forEach(function (a) {
        if (!a.getAttribute("data-wpsss-confirm")) {
          a.setAttribute("data-wpsss-confirm", copy.confirmMediaDelete || "此附件已进桶。永久删除会同时删掉桶内对象，没有回收站。先确认本地还有副本。仍要删除吗？");
        }
      });
    });
  }
  var deleteOne = document.querySelector("#delete-action a.submitdelete");
  if (deleteOne && document.querySelector(".wpsss-metabox .pill.ok")) {
    deleteOne.setAttribute(
      "data-wpsss-confirm",
      copy.confirmMediaDelete || "此附件已进桶。永久删除会同时删掉桶内对象，没有回收站。先确认本地还有副本。仍要删除吗？"
    );
  }

  var app = document.querySelector(".wpcy-app");
  if (!app) {
    return;
  }
  app.classList.add("is-ready");

  function markFocused(el, on) {
    if (!el || !el.classList || !el.matches) {
      return;
    }
    if (el.matches("input, select, textarea, button.btn, a.btn, button.toggle, .choice")) {
      el.classList.toggle("is-focused", on);
    }
  }
  app.addEventListener("focusin", function (event) {
    markFocused(event.target, true);
  });
  app.addEventListener("focusout", function (event) {
    markFocused(event.target, false);
  });

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
      toggle.classList.toggle("is-locked", toggle.disabled);
    }
  }

  function currentDriver(form) {
    var el = form.querySelector('[name$="[driver]"]:checked');
    return el ? el.value : cfg.savedDriver || "";
  }

  function currentScene(form) {
    var el = form.querySelector('[name$="[site_mode]"]:checked');
    return el ? el.value : cfg.savedSiteMode || "domestic";
  }

  function hasOption(select, value) {
    return !!(select && value && select.querySelector('option[value="' + value + '"]'));
  }

  function revealChoiceHelp(form, attr) {
    form.querySelectorAll('.choice input[data-wpsss-disclose="' + attr + '"]').forEach(function (radio) {
      var label = closest(radio, ".choice");
      var help = label ? label.querySelector(".d") : null;
      if (help) {
        help.hidden = !radio.checked;
      }
    });
  }

  function fillRegions(form, driver) {
    var select = form.querySelector(".js-wpsss-region");
    if (!select || !cfg.regions || !cfg.regions[driver]) {
      return;
    }
    var keep = select.value;
    var groups = cfg.regions[driver];
    var labels = { cn: "国内", overseas: "海外" };
    select.innerHTML = "";
    ["cn", "overseas"].forEach(function (gid) {
      var map = groups[gid] || {};
      var keys = Object.keys(map);
      if (!keys.length) {
        return;
      }
      var og = document.createElement("optgroup");
      og.label = labels[gid];
      keys.forEach(function (id) {
        var opt = document.createElement("option");
        opt.value = id;
        opt.textContent = map[id];
        og.appendChild(opt);
      });
      select.appendChild(og);
    });
    if (hasOption(select, keep)) {
      select.value = keep;
    } else if (driver === cfg.savedDriver && hasOption(select, cfg.savedRegion)) {
      select.value = cfg.savedRegion;
    } else if (cfg.defaults && cfg.defaults[driver]) {
      select.value = cfg.defaults[driver];
    }
  }

  function syncS3Preset(form, driver) {
    var select = form.querySelector(".js-wpsss-s3-preset");
    var lock = form.querySelector(".js-wpsss-s3-lock-label");
    var help = form.querySelector(".js-wpsss-s3-preset-help");
    var locked = cfg.lockedPreset && cfg.lockedPreset[driver];
    var copy = cfg.copy || {};
    if (select && locked) {
      select.value = locked;
      select.hidden = true;
      if (lock) {
        lock.hidden = false;
        lock.value = (cfg.s3Presets && cfg.s3Presets[locked]) || locked;
      }
      if (help) {
        help.textContent = driver === "gcs" ? copy.gcsHelp || "" : copy.tosHelp || "";
      }
      return;
    }
    if (select) {
      select.hidden = false;
    }
    if (lock) {
      lock.hidden = true;
    }
    if (help) {
      help.textContent = copy.s3Help || "";
    }
  }

  function syncAppId(form, driver) {
    var row = form.querySelector(".js-wpsss-app-id-field");
    if (!row) {
      return;
    }
    var preset = field(form, "s3_preset");
    var show = driver === "qcloud-cos" || (driver === "s3-compat" && preset && preset.value === "r2");
    row.hidden = !show;
    var label = row.querySelector(".js-wpsss-app-id-label");
    var help = row.querySelector(".js-wpsss-app-id-help");
    var copy = cfg.copy || {};
    var r2 = driver === "s3-compat";
    if (label) {
      label.textContent = r2 ? copy.appIdR2 || "" : copy.appIdCos || "";
    }
    if (help) {
      help.textContent = r2 ? copy.appIdR2Help || "" : copy.appIdCosHelp || "";
    }
  }

  function updateVendorSummary(form, driver) {
    var details = form.querySelector(".wpsss-vendor-more");
    if (!details) {
      return;
    }
    var title = details.querySelector(".js-wpsss-vendor-title");
    var help = details.querySelector(".js-wpsss-vendor-help");
    var tile = details.querySelector(".js-wpsss-vendor-tile");
    var dirty = driver !== cfg.savedDriver;
    if (title && cfg.drivers) {
      title.textContent = cfg.drivers[driver] || driver;
    }
    if (help) {
      help.textContent = dirty
        ? (cfg.copy && cfg.copy.preview) || "正在预览，尚未保存。"
        : (cfg.driverHelp && cfg.driverHelp[driver]) || "";
    }
    var radio = form.querySelector('.choice input[name$="[driver]"][value="' + driver + '"]');
    var src = radio ? closest(radio, ".choice") : null;
    var mark = src ? src.querySelector(".vendor-tile") : null;
    if (tile && mark) {
      tile.innerHTML = mark.innerHTML;
    }
    if (tile) {
      tile.classList.toggle("ok", !!cfg.configured && !dirty);
    }
  }

  function syncPreview(form) {
    var banner = document.querySelector(".wpsss-preview-banner");
    var dirty = currentDriver(form) !== cfg.savedDriver || currentScene(form) !== cfg.savedSiteMode;
    if (banner) {
      banner.hidden = !dirty;
      var text = banner.querySelector(".js-wpsss-preview-text");
      if (text && dirty) {
        var sm = currentScene(form);
        var dr = currentDriver(form);
        var modes = cfg.siteModes || {};
        var drivers = cfg.drivers || {};
        text.textContent =
          "正在预览「" +
          ((modes[sm] && modes[sm].label) || sm) +
          " · " +
          (drivers[dr] || dr) +
          "」，尚未保存。当前生效：「" +
          ((modes[cfg.savedSiteMode] && modes[cfg.savedSiteMode].label) || cfg.savedSiteMode) +
          " · " +
          (drivers[cfg.savedDriver] || cfg.savedDriver) +
          "」。";
      }
    }
    var meta = form.querySelector(".js-wpsss-save-meta");
    if (meta && cfg.copy) {
      meta.textContent = dirty ? cfg.copy.savePreview : cfg.copy.saveDefault;
    }
  }

  function applyScene(form) {
    if (!form) {
      return;
    }
    var mode = currentScene(form);
    var driver = currentDriver(form);
    var order = (cfg.groupOrder && cfg.groupOrder[mode]) || ["cn", "intl"];
    var grid = form.querySelector(".wpsss-vendor-grid");
    if (grid) {
      var groups = {};
      grid.querySelectorAll(".js-wpsss-vendor-group").forEach(function (g) {
        groups[g.getAttribute("data-wpsss-group")] = g;
      });
      order.forEach(function (gid) {
        if (groups[gid]) {
          groups[gid].hidden = false;
          grid.appendChild(groups[gid]);
        }
      });
      Object.keys(groups).forEach(function (gid) {
        if (order.indexOf(gid) !== -1) {
          return;
        }
        var keep = driver && groups[gid].querySelector('input[value="' + driver + '"]');
        groups[gid].hidden = !keep;
        if (keep) {
          grid.appendChild(groups[gid]);
        }
      });
    }
    revealChoiceHelp(form, "site_mode");
    syncPreview(form);
  }

  function applyDriver(form) {
    if (!form) {
      return;
    }
    var driver = currentDriver(form);
    var s3 = (cfg.s3Family || []).indexOf(driver) !== -1;
    form.querySelectorAll(".js-wpsss-for-s3").forEach(function (el) {
      el.hidden = !s3;
    });
    form.querySelectorAll(".js-wpsss-for-aliyun").forEach(function (el) {
      el.hidden = driver !== "aliyun-oss";
    });
    form.querySelectorAll(".js-wpsss-for-cos").forEach(function (el) {
      el.hidden = driver !== "qcloud-cos";
    });
    fillRegions(form, driver);
    var bucketHelp = form.querySelector(".js-wpsss-bucket-help");
    if (bucketHelp) {
      bucketHelp.textContent =
        (cfg.bucketHelp && cfg.bucketHelp[driver]) || (cfg.copy && cfg.copy.bucketFallback) || "仅填写桶名";
    }
    syncS3Preset(form, driver);
    syncAppId(form, driver);
    syncOssExtras(form);
    syncTtl(form);
    updateVendorSummary(form, driver);
    revealChoiceHelp(form, "driver");
    syncPreview(form);
  }

  function revertSaved(form) {
    var scene = form.querySelector('[name$="[site_mode]"][value="' + cfg.savedSiteMode + '"]');
    var drv = form.querySelector('[name$="[driver]"][value="' + cfg.savedDriver + '"]');
    if (scene) {
      scene.checked = true;
      syncChoice(scene);
    }
    if (drv) {
      drv.checked = true;
      syncChoice(drv);
    }
    applyScene(form);
    applyDriver(form);
  }

  function syncOssExtras(form) {
    if (!form) {
      return;
    }
    var driver = currentDriver(form);
    var region = form.querySelector(".js-wpsss-region");
    var origin = form.querySelector(".js-wpsss-origin-region");
    if (region && origin) {
      origin.hidden = driver !== "aliyun-oss" || String(region.value || "").indexOf("accelerate") === -1;
    }
    var auth = form.querySelector(".js-wpsss-oss-auth");
    var role = form.querySelector(".js-wpsss-ram-role");
    if (auth && role) {
      role.hidden = driver !== "aliyun-oss" || auth.value !== "ram";
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
        input.dispatchEvent(new Event("change", { bubbles: true }));
      } else if (already) {
        return;
      }
      syncChoice(input);
      if (input.getAttribute("data-wpsss-disclose")) {
        return;
      }
      var form = closest(input, "form.js-wpsss-instant");
      if (form) {
        postForm(form);
      }
    });
    input.addEventListener("change", function () {
      syncChoice(input);
      var disclose = input.getAttribute("data-wpsss-disclose");
      var settings = closest(input, "#wpsss-settings-form") || document.getElementById("wpsss-settings-form");
      if (disclose === "site_mode") {
        applyScene(settings);
        return;
      }
      if (disclose === "driver") {
        applyScene(settings);
        applyDriver(settings);
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

  var pullForm = app.querySelector(".js-wpsss-pull-form");
  if (pullForm) {
    var pullApply = pullForm.querySelectorAll("[data-wpsss-need-apply]");
    var pullBox = pullForm.querySelector('input[name="wpsss_pull_confirm"]');
    var pullDel = pullForm.querySelector(".js-wpsss-pull-delete");
    var pullGate = pullForm.querySelector(".js-wpsss-pull-gate");
    var pullExtra = pullForm.querySelector(".js-wpsss-pull-delete-confirm");
    var pullDelOk = pullForm.querySelector('input[name="wpsss_pull_delete_confirm"]');
    var pullHint = pullForm.querySelector(".js-wpsss-pull-hint");
    var pullConfirmText = pullForm.querySelector(".js-wpsss-pull-confirm-text");
    var pullCopy = {
      skip: {
        hint: "仅当 uploads 目录缺少对应文件时从存储桶下载；已有文件保持不变，不修改对外 URL。",
        confirm: "",
        dialog: "",
      },
      rename: {
        hint: "先把现有本地文件改名为「原名-local-时间戳」保留，再写入桶里的文件；媒体库条目与存储路径不变。",
        confirm: "我确认先把已有本地文件改名保留，再写入桶里的文件",
        dialog: "已有本地文件会先改名为「原名-local-时间戳」保留，再写入桶里的文件。是否继续？",
      },
      overwrite: {
        hint: "用存储桶中的文件直接替换 uploads 中的同名文件；替换后无法从回收站恢复。",
        confirm: "我确认覆盖 uploads 中的同名文件",
        dialog: "将覆盖 uploads 中的同名文件，且无法从回收站恢复；默认不删除存储桶对象。是否继续？",
      },
    };
    var pullOne = pullForm.querySelector(".js-wpsss-pull-one");
    var pullId = pullForm.querySelector(".js-wpsss-pull-id");
    var pullScopeHint = pullForm.querySelector(".js-wpsss-pull-scope-hint");
    var pullGo = pullForm.querySelectorAll(".js-wpsss-pull-go");
    function pullMode() {
      var checked = pullForm.querySelector(".js-wpsss-pull-exist:checked");
      return checked && checked.value ? checked.value : "skip";
    }
    function pullScope() {
      var checked = pullForm.querySelector(".js-wpsss-pull-scope:checked");
      return checked && checked.value === "one" ? "one" : "batch";
    }
    function updatePull() {
      var mode = pullMode();
      var scope = pullScope();
      var row = pullCopy[mode] || pullCopy.skip;
      pullForm.setAttribute("data-mode", mode);
      pullForm.setAttribute("data-scope", scope);
      pullForm.querySelectorAll(".wpsss-pull-seg label").forEach(function (label) {
        var radio = label.querySelector("input");
        label.classList.toggle("on", !!(radio && radio.checked));
      });
      if (pullScopeHint) {
        pullScopeHint.textContent = scope === "one"
          ? "仅处理指定的一个附件。编号可在「媒体 → 媒体库」中打开文件后查看（例如 128）。"
          : "对已进桶的附件按媒体库编号从新到旧拉回，一次拉一个；关掉页面即停，再点「拉回」可继续。";
      }
      if (pullOne) {
        pullOne.hidden = scope !== "one";
      }
      if (pullId) {
        pullId.disabled = scope !== "one";
        if (scope !== "one") {
          pullId.value = "";
        }
      }
      if (pullHint) {
        pullHint.textContent = row.hint;
        pullHint.classList.toggle("is-warn", mode === "overwrite");
      }
      if (pullGate) {
        pullGate.hidden = mode === "skip";
      }
      if (mode === "skip" && pullDel) {
        pullDel.checked = false;
        if (pullDelOk) {
          pullDelOk.checked = false;
        }
      }
      if (pullExtra) {
        pullExtra.hidden = !(pullDel && pullDel.checked && mode !== "skip");
      }
      if (pullConfirmText) {
        pullConfirmText.textContent = row.confirm;
      }
      var ok = mode === "skip" || !!(pullBox && pullBox.checked);
      if (pullDel && pullDel.checked && pullDelOk && !pullDelOk.checked) {
        ok = false;
      }
      if (scope === "one" && !(pullId && parseInt(pullId.value, 10) > 0)) {
        ok = false;
      }
      pullForm.classList.toggle("is-confirmed", ok);
      pullGo.forEach(function (btn) {
        if (!btn.getAttribute("data-wpsss-need-apply")) {
          btn.disabled = scope === "one" && !(pullId && parseInt(pullId.value, 10) > 0);
        }
      });
      pullApply.forEach(function (btn) {
        btn.disabled = !ok;
        var label = btn.getAttribute("data-wpsss-label");
        var labelDelete = btn.getAttribute("data-wpsss-label-delete");
        if (label && labelDelete) {
          btn.textContent = pullDel && pullDel.checked ? labelDelete : label;
        }
        if (row.dialog) {
          btn.setAttribute("data-wpsss-confirm", row.dialog);
        } else {
          btn.removeAttribute("data-wpsss-confirm");
        }
      });
    }
    if (pullBox) {
      pullBox.addEventListener("change", updatePull);
    }
    if (pullDel) {
      pullDel.addEventListener("change", updatePull);
    }
    if (pullDelOk) {
      pullDelOk.addEventListener("change", updatePull);
    }
    pullForm.querySelectorAll(".js-wpsss-pull-exist").forEach(function (radio) {
      radio.addEventListener("change", updatePull);
    });
    pullForm.querySelectorAll(".js-wpsss-pull-scope").forEach(function (radio) {
      radio.addEventListener("change", updatePull);
    });
    if (pullId) {
      pullId.addEventListener("input", updatePull);
    }
    updatePull();
  }

  function bindOffload(form) {
    var runBtn = form.querySelector(".js-wpsss-offload-run");
    var stopBtn = form.querySelector(".js-wpsss-offload-stop");
    var progress = form.querySelector(".js-wpsss-offload-progress");
    var bar = form.querySelector(".js-wpsss-offload-bar");
    var barFill = bar ? bar.querySelector("span") : null;
    if (!runBtn || !cfg.ajaxUrl || !cfg.offloadNonce) {
      return;
    }
    var running = false;
    var halt = false;
    var failStreak = 0;
    var totals = { ok: 0, fail: 0, skip: 0 };
    var baseline = 0;

    function setBar(done, remaining) {
      if (!bar || !barFill) {
        return;
      }
      bar.hidden = false;
      var pct = baseline > 0 ? Math.min(100, Math.round((done / baseline) * 100)) : 0;
      barFill.style.width = pct + "%";
      return pct;
    }

    function setProgress(text) {
      if (progress) {
        progress.textContent = text;
      }
    }

    function idle() {
      running = false;
      form.classList.remove("is-busy");
      form.querySelectorAll("button").forEach(function (btn) {
        if (btn === stopBtn) {
          return;
        }
        btn.disabled = false;
      });
      if (stopBtn) {
        stopBtn.hidden = true;
        stopBtn.disabled = false;
      }
    }

    function tick() {
      if (!running || halt) {
        idle();
        if (halt && (totals.ok + totals.fail + totals.skip) > 0) {
          setProgress("已暂停。本轮成功 " + totals.ok + "，失败 " + totals.fail + "，跳过 " + totals.skip + "。");
        }
        return;
      }
      var body = new URLSearchParams();
      body.set("action", "wpsss_offload_tick");
      body.set("nonce", cfg.offloadNonce);
      if (form.querySelector('[name="wpsss_retry"]') || (form.querySelector('[name="source"]') && form.querySelector('[name="source"]').value === "failed")) {
        body.set("source", "failed");
      }
      fetch(cfg.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (json) {
          if (!json || !json.success) {
            running = false;
            idle();
            setProgress((json && json.data && json.data.message) || "补传已停止：服务器没有返回结果，请稍后重试。");
            return;
          }
          var data = json.data || {};
          if (data.busy) {
            setProgress("队列正被另一个任务使用，稍候自动继续…");
            window.setTimeout(tick, 2500);
            return;
          }
          totals.ok += data.ok || 0;
          totals.fail += data.fail || 0;
          totals.skip += data.skip || 0;
          var left = body.get("source") === "failed" ? (data.failed || 0) : (data.pending || 0);
          var processed = totals.ok + totals.fail + totals.skip;
          if (baseline === 0 && (processed + left) > 0) {
            baseline = processed + left;
          }
          var pct = setBar(processed, left);
          if ((data.fail || 0) > 0 && !(data.ok > 0)) {
            failStreak++;
          } else {
            failStreak = 0;
          }
          if (failStreak >= 5) {
            running = false;
            idle();
            setProgress("页面补传连续 5 轮失败，已暂停。请到「工具 → 排障」查明原因后再重试。");
            return;
          }
          var left = body.get("source") === "failed" ? data.failed : data.pending;
          setProgress((typeof pct === "number" ? pct + "% · " : "") + "本轮成功 " + totals.ok + "，失败 " + totals.fail + "，还剩 " + (left || 0) + "。");
          if (data.error) {
            setProgress((progress ? progress.textContent + " " : "") + data.error);
          }
          if (data.done) {
            running = false;
            idle();
            if (barFill) {
              barFill.style.width = "100%";
            }
            setProgress("本轮结束：成功 " + totals.ok + "，失败 " + totals.fail + "，跳过 " + totals.skip + "。");
            var hint = form.querySelector(".js-wpsss-offload-hint");
            if (hint && (left || 0) === 0) {
              hint.textContent = (data.failed || 0) > 0
                ? "待补传已清完，剩 " + (data.failed || 0) + " 个失败项。到「工具 → 排障」查明原因，再点「重试失败项」。"
                : "全部附件已进桶，新上传会自动进桶。";
            }
            if (runBtn && (left || 0) === 0) {
              runBtn.disabled = true;
            }
            return;
          }
          var delay = (data.fail || 0) > 0 ? Math.min(5000, 200 * Math.pow(2, failStreak)) : 40;
          window.setTimeout(tick, delay);
        })
        .catch(function () {
          running = false;
          idle();
          setProgress("网络中断，已暂停。可再点「开始补传」继续。");
        });
    }

    if (stopBtn) {
      stopBtn.addEventListener("click", function () {
        halt = true;
        stopBtn.disabled = true;
        var stopBody = new URLSearchParams();
        stopBody.set("action", "wpsss_offload_tick");
        stopBody.set("nonce", cfg.offloadNonce);
        stopBody.set("stop", "1");
        fetch(cfg.ajaxUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: stopBody.toString(),
        }).catch(function () {});
      });
    }

    form.addEventListener(
      "submit",
      function (event) {
        var btn = event.submitter;
        if (btn && btn.name === "wpsss_offload_reset") {
          return;
        }
        if (!cfg.offloadNonce) {
          return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (running) {
          return;
        }
        totals = { ok: 0, fail: 0, skip: 0 };
        failStreak = 0;
        baseline = 0;
        if (barFill) {
          barFill.style.width = "0%";
        }
        halt = false;
        running = true;
        form.classList.add("is-busy");
        if (runBtn) {
          runBtn.disabled = true;
        }
        if (stopBtn) {
          stopBtn.hidden = false;
          stopBtn.disabled = false;
        }
        setProgress("开始补传…");
        tick();
      },
      true
    );
  }

  app.querySelectorAll(".js-wpsss-offload-form").forEach(bindOffload);

  /**
   * 批量拉回走 admin-ajax 一次拉一个附件；预览与「单个附件」仍走同步 POST。
   * 挂在通用提交处理器之前注册（同 capture 阶段按注册顺序执行），
   * 先问确认再接管提交，避免通用处理器把表单按普通 POST 发出去。
   */
  function bindPullQueue(form) {
    var runBtn = form.querySelector(".js-wpsss-pull-run");
    var stopBtn = form.querySelector(".js-wpsss-pull-stop");
    var progress = form.querySelector(".js-wpsss-pull-progress");
    var bar = form.querySelector(".js-wpsss-pull-bar");
    if (!runBtn || !cfg.ajaxUrl || !cfg.pullNonce) {
      return;
    }
    var running = false;
    var halt = false;
    var failStreak = 0;
    var totals = { ok: 0, fail: 0, skip: 0, renamed: 0 };

    function setProgress(text) {
      if (progress) {
        progress.textContent = text;
      }
    }

    function idle() {
      running = false;
      form.classList.remove("is-busy");
      form.querySelectorAll("button").forEach(function (btn) {
        if (btn === stopBtn) {
          return;
        }
        btn.disabled = false;
      });
      if (stopBtn) {
        stopBtn.hidden = true;
        stopBtn.disabled = false;
      }
      if (bar) {
        bar.hidden = true;
      }
    }

    function isBatchScope() {
      var checked = form.querySelector(".js-wpsss-pull-scope:checked");
      return !checked || checked.value !== "one";
    }

    function tick() {
      if (!running || halt) {
        idle();
        if (halt && (totals.ok + totals.fail + totals.skip) > 0) {
          setProgress("已暂停。本轮已拉回 " + totals.ok + "，失败 " + totals.fail + "。");
        }
        return;
      }
      var body = new URLSearchParams(new FormData(form));
      body.set("action", "wpsss_pull_tick");
      body.set("nonce", cfg.pullNonce);
      fetch(cfg.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (json) {
          if (!json || !json.success) {
            running = false;
            idle();
            setProgress((json && json.data && json.data.message) || "拉回已停止：服务器没有返回结果，请稍后重试。");
            return;
          }
          var data = json.data || {};
          totals.ok += data.ok || 0;
          totals.fail += data.fail || 0;
          totals.skip += data.skip || 0;
          totals.renamed += data.renamed || 0;
          if ((data.fail || 0) > 0 && !(data.ok > 0)) {
            failStreak++;
          } else {
            failStreak = 0;
          }
          if (failStreak >= 5) {
            running = false;
            idle();
            setProgress("页面补传连续 5 轮失败，已暂停。请到「工具 → 排障」查明原因后再继续。");
            return;
          }
          var line = "本轮已拉回 " + totals.ok + "，失败 " + totals.fail;
          if (totals.renamed > 0) {
            line += "，改名保留 " + totals.renamed;
          }
          line += data.cursor > 0 ? "。处理到编号 " + data.cursor + "。" : "。";
          setProgress(line);
          if (data.error) {
            setProgress((progress ? progress.textContent + " " : "") + data.error);
          }
          if (data.done) {
            running = false;
            idle();
            var doneLine = "拉回结束。已拉回 " + totals.ok + "，失败 " + totals.fail + "，跳过 " + totals.skip + "。";
            setProgress(doneLine);
            return;
          }
          var delay = (data.fail || 0) > 0 ? Math.min(5000, 200 * Math.pow(2, failStreak)) : 60;
          window.setTimeout(tick, delay);
        })
        .catch(function () {
          running = false;
          idle();
          setProgress("网络中断，已暂停。可再点「拉回」继续。");
        });
    }

    if (stopBtn) {
      stopBtn.addEventListener("click", function () {
        halt = true;
        stopBtn.disabled = true;
      });
    }

    form.addEventListener(
      "submit",
      function (event) {
        var btn = event.submitter;
        if (!btn || btn.name !== "wpsss_pull_apply" || btn.value !== "1") {
          return;
        }
        if (!isBatchScope()) {
          return;
        }
        var msg = btn.getAttribute("data-wpsss-confirm") || form.getAttribute("data-wpsss-confirm");
        if (form.querySelector('input[name="wpsss_pull_delete"]:checked')) {
          msg = copy.confirmPullDelete || msg;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        if (msg && !askConfirm(msg)) {
          return;
        }
        if (running) {
          return;
        }
        totals = { ok: 0, fail: 0, skip: 0, renamed: 0 };
        failStreak = 0;
        halt = false;
        running = true;
        form.classList.add("is-busy");
        if (runBtn) {
          runBtn.disabled = true;
        }
        if (stopBtn) {
          stopBtn.hidden = false;
          stopBtn.disabled = false;
        }
        if (bar) {
          bar.hidden = false;
        }
        setProgress("开始拉回…");
        tick();
      },
      true
    );
  }

  if (pullForm) {
    bindPullQueue(pullForm);
  }

  app.querySelectorAll("form").forEach(function (form) {
    form.addEventListener(
      "submit",
      function (event) {
        var btn = event.submitter;
        var msg = (btn && btn.getAttribute("data-wpsss-confirm")) || form.getAttribute("data-wpsss-confirm");
        if (form.querySelector('input[name="wpsss_pull_delete"]:checked')) {
          msg = copy.confirmPullDelete || msg;
        }
        if (msg && !askConfirm(msg)) {
          event.preventDefault();
          event.stopImmediatePropagation();
          return;
        }
        form.classList.add("is-busy");
        // 提交条目列表在 submit 处理器跑完之后才构建：此刻禁用 submitter 会让它的
        // name/value 不进 POST（「从头」会变成普通补传）。submitter 延后一拍再禁。
        var submitter = event.submitter;
        form.querySelectorAll("button").forEach(function (item) {
          if (item !== submitter) {
            item.disabled = true;
          }
        });
        if (submitter) {
          window.setTimeout(function () {
            submitter.disabled = true;
          }, 0);
        }
      },
      true
    );
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
    if (/preview_/.test(window.location.search)) {
      restoreDraft(settingsForm);
    }
    applyScene(settingsForm);
    applyDriver(settingsForm);
    syncTtl(settingsForm);
    syncOssExtras(settingsForm);
    syncProtectHint(settingsForm);
    fillCosAppId(settingsForm);
    var revert = document.querySelector(".js-wpsss-revert");
    if (revert) {
      revert.addEventListener("click", function (event) {
        event.preventDefault();
        revertSaved(settingsForm);
      });
    }
    var s3preset = settingsForm.querySelector(".js-wpsss-s3-preset");
    if (s3preset) {
      s3preset.addEventListener("change", function () {
        syncAppId(settingsForm, currentDriver(settingsForm));
      });
    }
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
              inspectHint(settingsForm, (json && json.data && json.data.message) || "读取桶信息失败，请稍后重试。", false);
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
              notes.length ? notes.join(" ") : "已填入建议值，请点「保存设置」。",
              true
            );
          })
          .catch(function () {
            inspectBtn.disabled = false;
            inspectHint(settingsForm, "读取桶信息失败，请稍后重试。", false);
          });
      });
    }
  }
})();
