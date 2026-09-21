#!/usr/bin/env python3
"""Lint a wenpai-admin-ui kit tree. 0 files → SKIP, never PASS."""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path


def skip(reason: str) -> int:
    print(f"SKIP {reason}")
    return 0


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default="", help="kit repo root")
    ap.add_argument("--plugin-root", default="", help="plugin root; lints lib/wenpai-admin-ui")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()
    if args.plugin_root:
        root = Path(args.plugin_root).resolve() / "lib" / "wenpai-admin-ui"
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

    findings: list[dict] = []

    css_files = sorted(css_dir.glob("*.css")) if css_dir.is_dir() else []
    php_files = sorted(php_dir.glob("*.php")) if php_dir.is_dir() else []
    if not css_files and not php_files:
        return skip("0 css/php files")

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
        # load.php may not contain handle; loader must
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

    result = {
        "root": str(root),
        "css": len(css_files),
        "php": len(php_files),
        "findings": findings,
        "status": "FAIL" if findings else "PASS",
    }
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(f"{result['status']} css={result['css']} php={result['php']} findings={len(findings)}")
        for item in findings:
            print(f"  {item['kind']} {item['file']}: {item['why']}")
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
