#!/usr/bin/env python3
"""
Generate task files and verification script from Plan subagent output.

Usage:
    python generate_tasks.py <feature-name> <json-file>

Input JSON format (from Plan subagent):
[
    {
        "id": "01",
        "title": "Setup project structure",
        "description": "Initialize npm project and install dependencies",
        "acceptance_criteria": ["npm install succeeds", "project builds"],
        "files": ["package.json", "tsconfig.json"],
        "depends_on": [],
        "verification": ["npm install", "npm run build"]
    },
    ...
]
"""

import json
import os
import sys
from pathlib import Path


def _verification_section(task):
    """Generate verification section for a task markdown file."""
    verification_cmds = task.get("verification", [])
    if not verification_cmds:
        return '⚠️ **未定义验证命令** — 请在实现前补充可执行的验证命令。\n'

    lines = [
        '<!-- 合约：feature-implement 逐条执行以下命令，退出码 0=通过，非0=失败。不得自行添加或修改命令。 -->\n',
        '```bash',
    ]
    lines.extend(verification_cmds)
    lines.append('```\n')
    return '\n'.join(lines)


def main():
    if len(sys.argv) < 3:
        print("Usage: generate_tasks.py <feature-name> <json-file>")
        sys.exit(1)

    feature_name = sys.argv[1]
    json_file = sys.argv[2]

    with open(json_file) as f:
        tasks = json.load(f)

    base_dir = Path("docs") / "features" / feature_name
    tasks_dir = base_dir / "tasks"

    # Create directories
    tasks_dir.mkdir(parents=True, exist_ok=True)

    # Write each task file
    for task in tasks:
        tid = task["id"]
        title = task["title"]
        slug = f"{tid}-{title.lower().replace(' ', '-').replace('/', '-')}"
        content = f"""# {title}

## 描述

{task["description"]}

## 前置依赖

{chr(10).join(f"- {d}" for d in (task.get("depends_on", []) or ["无"]))}

## 验收标准

{chr(10).join(f"- [ ] {c}" for c in task["acceptance_criteria"])}

## 涉及文件

{chr(10).join(f"- {f}" for f in task.get("files", []))}

## 验证方式

{_verification_section(task)}
"""

        file_path = tasks_dir / f"{slug}.md"
        file_path.write_text(content)
        print(f"  ✓ {file_path}")

    # Write README index
    readme_lines = [
        f"# {feature_name} - 任务拆解\n",
        f"\n总任务数: {len(tasks)}\n",
        "\n| # | 任务 | 状态 | 前置依赖 |",
        "|---|------|------|----------|",
    ]
    for task in tasks:
        deps = ", ".join(task.get("depends_on", [])) or "-"
        readme_lines.append(f"| {task['id']} | {task['title']} | pending | {deps} |")

    (tasks_dir / "README.md").write_text("\n".join(readme_lines) + "\n")
    print(f"  ✓ {tasks_dir / 'README.md'}")

    # Basic verify.sh
    verify_lines = [
        "#!/bin/bash",
        f"# 功能验证脚本 - {feature_name}",
        f"# 用法: bash docs/features/{feature_name}/verify.sh",
        "#",
        "# 设计原则：",
        "# 1. 不使用 set -e（需运行全部验证步骤并汇总结果）",
        "# 2. 每条验证的退出码 0=通过，非0=失败",
        "# 3. 由 generate_tasks.py 根据 Plan subagent 的 verification 字段自动生成",
        "",
        "set -o pipefail",
        "",
        "PASS=0",
        "FAIL=0",
        "",
        "run_and_check() {",
        '    local desc="$1"',
        "    shift",
        "    local output",
        '    output=$("$@" 2>&1)',
        "    local code=$?",
        '    if [ "$code" -eq 0 ]; then',
        '        echo "✅ PASS: $desc"',
        "        PASS=$((PASS + 1))",
        "    else",
        '        echo "❌ FAIL: $desc (exit=$code)"',
        '        echo "   $output" | head -5',
        "        FAIL=$((FAIL + 1))",
        "    fi",
        "}",
        "",
        "check_eq() {",
        '    local desc="$1"',
        '    local expected="$2"',
        '    local actual="$3"',
        '    if [ "$actual" = "$expected" ]; then',
        '        echo "✅ PASS: $desc"',
        "        PASS=$((PASS + 1))",
        "    else",
        '        echo "❌ FAIL: $desc (expected \'$expected\', got \'$actual\')"',
        "        FAIL=$((FAIL + 1))",
        "    fi",
        "}",
        "",
        f'echo "===== {feature_name} 验证开始 ====="',
        'echo ""',
        "",
        "# ==================== 任务级验证 ====================",
        "",
    ]

    for task in tasks:
        tid = task["id"]
        title = task["title"]
        verification_cmds = task.get("verification", [])

        verify_lines.append(f'echo "--- 任务 {tid}: {title} ---"')

        if verification_cmds:
            for cmd in verification_cmds:
                # Escape single quotes in the command for bash
                escaped = cmd.replace("'", "'\\''")
                verify_lines.append(f"if {cmd} 2>&1; then")
                verify_lines.append(f"    echo '  ✅ PASS: {escaped}'")
                verify_lines.append("    PASS=$((PASS + 1))")
                verify_lines.append("else")
                verify_lines.append(f"    echo '  ❌ FAIL: {escaped}'")
                verify_lines.append("    FAIL=$((FAIL + 1))")
                verify_lines.append("fi")
        else:
            verify_lines.append(f"echo '  ⚠️  未定义验证命令'")

        verify_lines.append("")

    verify_lines.extend([
        'echo ""',
        f'echo "===== {feature_name} 验证结束 ====="',
        'echo "通过: $PASS, 失败: $FAIL"',
        "[ $FAIL -eq 0 ] || exit 1",
    ])

    verify_path = base_dir / "verify.sh"
    verify_path.write_text("\n".join(verify_lines) + "\n")
    verify_path.chmod(0o755)
    print(f"  ✓ {verify_path}")
    print(f"\nDone! Generated {len(tasks)} task(s) and verify.sh under {base_dir}")


if __name__ == "__main__":
    main()
