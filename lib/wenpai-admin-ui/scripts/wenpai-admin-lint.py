#!/usr/bin/env python3
"""Lint a wenpai-admin-ui kit tree (and, with --plugin-root, product admin pages).

0 css/php files → SKIP, never PASS.
0 prototype html / 0 plugin templates → SKIP that subcheck; do not treat the skip as PASS.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

TEMPLATE_SKIP_PARTS = ("gutenberg", "block-editor", "list-table")


def skip(reason: str) -> int:
    print(f"SKIP {reason}")
    return 0


def lint_kit(root: Path) -> tuple[list[dict], int, int]:
    findings: list[dict] = []
    css_dir = root / "css"
    php_dir = root / "php"
    css_files = sorted(css_dir.glob("*.css")) if css_dir.is_dir() else []
    php_files = sorted(php_dir.glob("*.php")) if php_dir.is_dir() else []

    banned_css = (
        (":root", "token on :root"),
        (".wpcy-", "old wpcy prefix"),
        (".wpsss-", "product selector in kit"),
        (".wpslug-", "product selector in kit"),
        (".stat-a", "leaf console primitive"),
        (".hero-w", "leaf hero"),
        (".eco", "leaf eco block"),
    )
    for f in css_files:
        text = f.read_text(errors="replace")
        for needle, why in banned_css:
            if needle == ".eco":
                if ".eco" in text or "eco-" in text:
                    findings.append({"file": str(f), "kind": "CSS", "why": why})
                continue
            if needle in text:
                findings.append({"file": str(f), "kind": "CSS", "why": why, "needle": needle})

    handle_ok = False
    class_guard = 0
    class_decl = 0
    for f in php_files:
        text = f.read_text(errors="replace")
        if "wenpai-admin-ui" in text and "STYLE_HANDLE" in text:
            handle_ok = True
        if "class Wenpai_Admin_" in text:
            class_decl += 1
            if "class_exists" in text:
                class_guard += 1
        if "function wenpai_admin_ui_register" in text and "function_exists" not in text:
            findings.append({"file": str(f), "kind": "PHP", "why": "register without function_exists"})

    if class_decl and class_guard < class_decl:
        findings.append(
            {
                "file": str(php_dir),
                "kind": "PHP",
                "why": f"class_exists missing ({class_guard}/{class_decl})",
            }
        )
    if php_files and not handle_ok:
        loader = php_dir / "class-wenpai-admin-loader.php"
        if loader.exists() and "wenpai-admin-ui" not in loader.read_text(errors="replace"):
            findings.append({"file": str(loader), "kind": "PHP", "why": "handle not wenpai-admin-ui"})

    shell = php_dir / "class-wenpai-admin-shell.php"
    if shell.is_file():
        shell_text = shell.read_text(errors="replace")
        if "wenpai-main" not in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell missing wenpai-main"})
        if 'class="foot"' not in shell_text and "class='foot'" not in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell missing footer.foot"})
        if "wenpai-foot" in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell uses wenpai-foot not foot"})
        if "wenpai-mode" in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell uses wenpai-mode not mode"})

    js_file = root / "js" / "wenpai-admin.js"
    if js_file.is_file():
        js_text = js_file.read_text(errors="replace")
        if "form.action" in js_text:
            findings.append({"file": str(js_file), "kind": "JS", "why": "js reads form.action"})

    wp_css = css_dir / "wenpai-admin-wp.css"
    if wp_css.is_file():
        wp_text = wp_css.read_text(errors="replace")
        if "max-width: none" not in wp_text and "max-width:none" not in wp_text:
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing .card max-width none"})
        if "margin-top: 12px" not in wp_text and "margin-top:12px" not in wp_text:
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing notice sibling 12px"})

    return findings, len(css_files), len(php_files)


def lint_prototypes(root: Path) -> tuple[list[dict], str | None]:
    proto = root / "prototypes"
    if not proto.is_dir():
        return [], "0 prototype html"
    htmls = sorted(p for p in proto.rglob("*.html") if p.is_file())
    if not htmls:
        return [], "0 prototype html"
    findings: list[dict] = []
    for f in htmls:
        text = f.read_text(errors="replace")
        if "wenpai-admin.css" not in text:
            continue
        i_css = text.find("wenpai-admin.css")
        i_wp = text.find("wenpai-admin-wp.css")
        if i_wp < 0:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype missing wenpai-admin-wp.css"})
        elif i_wp < i_css:
            findings.append({"file": str(f), "kind": "HTML", "why": "overlay before surface css"})
        if "wenpai-ui-page" not in text:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype body missing wenpai-ui-page"})
    return findings, None


def _under_templates(plugin_root: Path, path: Path) -> bool:
    try:
        return path.relative_to(plugin_root).parts[0] == "templates"
    except ValueError:
        return False


def iter_plugin_page_files(plugin_root: Path) -> list[Path]:
    out: list[Path] = []
    templates = plugin_root / "templates"
    if templates.is_dir():
        out.extend(sorted(templates.rglob("*.php")))
        out.extend(sorted(templates.rglob("*.html")))
    includes = plugin_root / "includes"
    if includes.is_dir():
        out.extend(sorted(includes.rglob("*.php")))
    src = plugin_root / "src"
    if src.is_dir():
        out.extend(sorted(src.rglob("*.php")))
    js_dir = plugin_root / "assets" / "js"
    if js_dir.is_dir():
        out.extend(sorted(js_dir.rglob("*.js")))
    cleaned: list[Path] = []
    for path in out:
        posix = path.as_posix()
        if "lib/wenpai-admin-ui" in posix:
            continue
        cleaned.append(path)
    return cleaned


def lint_plugin_pages(plugin_root: Path) -> tuple[list[dict], str | None]:
    files = iter_plugin_page_files(plugin_root)
    if not files:
        return [], "0 plugin templates/js"
    findings: list[dict] = []
    for path in files:
        rel = path.relative_to(plugin_root).as_posix().lower()
        text = path.read_text(errors="replace")
        kind = "JS" if path.suffix == ".js" else "PHP"
        skip_form = any(part in rel for part in TEMPLATE_SKIP_PARTS)
        in_templates = _under_templates(plugin_root, path)
        kit_page = in_templates or ("wenpai-app" in text) or ("wenpai-main" in text)
        if (not skip_form) and kit_page and "form-table" in text:
            findings.append({"file": str(path), "kind": kind, "why": "kit page uses form-table"})
        if "wpcy-app" in text:
            findings.append({"file": str(path), "kind": kind, "why": "product still uses wpcy-app"})
    return findings, None


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default="", help="kit repo root")
    ap.add_argument("--plugin-root", default="", help="plugin root; lints lib/wenpai-admin-ui plus templates")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    plugin_root: Path | None = None
    if args.plugin_root:
        plugin_root = Path(args.plugin_root).resolve()
        root = plugin_root / "lib" / "wenpai-admin-ui"
        if not root.is_dir():
            print(f"FAIL missing {root}")
            return 1
    elif args.root:
        root = Path(args.root).resolve()
    else:
        print("usage: --root <kit> or --plugin-root <plugin>", file=sys.stderr)
        return 2

    css_dir = root / "css"
    php_dir = root / "php"
    if not css_dir.is_dir() and not php_dir.is_dir():
        return skip("no css/ or php/ under --root")

    findings, n_css, n_php = lint_kit(root)
    if not n_css and not n_php:
        return skip("0 css/php files")

    proto_findings, proto_skip = lint_prototypes(root)
    findings.extend(proto_findings)

    plugin_skip: str | None = None
    if plugin_root is not None:
        page_findings, plugin_skip = lint_plugin_pages(plugin_root)
        findings.extend(page_findings)

    if proto_skip:
        proto_status = "SKIP"
    elif proto_findings:
        proto_status = "FAIL"
    elif (root / "prototypes").is_dir():
        proto_status = "PASS"
    else:
        proto_status = "SKIP"

    if plugin_root is None:
        plugin_status = None
    elif plugin_skip:
        plugin_status = "SKIP"
    elif any(item.get("why") in {"kit page uses form-table", "product still uses wpcy-app"} for item in findings):
        plugin_status = "FAIL"
    else:
        plugin_status = "PASS"

    result = {
        "root": str(root),
        "css": n_css,
        "php": n_php,
        "findings": findings,
        "status": "FAIL" if findings else "PASS",
        "prototypes": proto_status,
        "plugin_pages": plugin_status,
        "plugin_pages_skip": plugin_skip,
        "prototypes_skip": proto_skip,
    }
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        extra = f" prototypes={proto_status}"
        if plugin_status is not None:
            extra += f" plugin_pages={plugin_status}"
            if plugin_skip:
                extra += f" ({plugin_skip})"
        print(f"{result['status']} css={n_css} php={n_php} findings={len(findings)}{extra}")
        for item in findings:
            print(f"  {item['kind']} {item['file']}: {item['why']}")
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
