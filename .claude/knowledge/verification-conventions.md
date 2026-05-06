# Verification Conventions

所有 skill 在设计和执行验证时必须遵循的统一约定。本文档是唯一权威来源。

## 退出码约定

| 命令 | 退出码 0 含义 | 退出码非 0 含义 |
|------|--------------|-----------------|
| `php bin/phpunit` | 全部测试通过 | 有测试失败或错误 |
| `vendor/bin/php-cs-fixer fix --dry-run` | 无需修复 | 有文件需要修复 |
| `vendor/bin/phpstan analyze` | 无错误 | 有静态分析错误 |
| `grep -q pattern file` | 匹配到 | 未匹配到 |
| `php -l file.php` | 语法正确 | 语法错误 |
| `php bin/console debug:config <alias>` | 配置有效 | 配置错误或 alias 不存在 |
| `php bin/console debug:router` | 路由有效 | 路由错误 |
| `php bin/console doctrine:schema:validate` | Schema 有效 | Schema 与实体不匹配 |

## Shell 脚本约定

### 聚合报告脚本（verify.sh / integration-verify）

```bash
#!/bin/bash
set -o pipefail  # 管道中任一命令失败则整体失败
# 不使用 set -e —— 每条命令独立判定，聚合 PASS/FAIL

run_and_check() {
    local desc="$1"
    shift
    local output
    output=$("$@" 2>&1)
    local code=$?
    if [ "$code" -eq 0 ]; then
        echo "✅ PASS: $desc"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: $desc (exit=$code)"
        echo "   $output" | head -5
        FAIL=$((FAIL + 1))
    fi
}
```

### 任务级脚本（单任务验证，fail-fast）

```bash
#!/bin/bash
set -eo pipefail  # 任一命令失败即中止，管道中任一命令失败也中止
```

### 管道到 tee 时取脚本退出码

```bash
# 正确：用 PIPESTATUS[0] 取管道第一个命令（脚本）的退出码
bash /tmp/verify.sh 2>&1 | tee artifacts/verify.log
VERIFY_EXIT=${PIPESTATUS[0]}

# 错误：$? 取的是 tee 的退出码（永远是 0）
# VERIFY_EXIT=$?   ← 有 bug，不要用
```

### 直接重定向（替代方案，不需要 PIPESTATUS）

```bash
bash /tmp/verify.sh > artifacts/verify.log 2>&1
VERIFY_EXIT=$?  # 这里直接取没问题，因为没有管道
```

## 三级预检协议

feature-implement 在执行任务验证命令前，必须按三级分类检查每条命令：

### Level 1 — 反模式（🛑 暂停，报告用户，禁止自行修正）

| 反模式 | 检测方式 | 示例 |
|--------|----------|------|
| 手动步骤 | 命令含 `手动`/`manual`/`浏览器` | `# 手动 curl 测试各端点` |
| 占位符/TODO | 命令含 `请参见`/`TODO`/`⚠️` | `请参见 verify.sh 中对应的验证步骤` |
| 只输出不判定 | 纯 echo/printf 语句 | `echo "检查输出是否正确"` |
| 空验证 | 验证方式为空或仅为注释 | `<!-- TODO -->` |

### Level 2 — 事实性错误（🔧 允许修正，记录审计追踪）

| 错误类型 | 检测方式 | 修正原则 |
|----------|----------|----------|
| 猜测 bundle alias | Symfony `debug:config <非标准alias>` | 用 `bin/console debug:config --list` 查出真实 alias |
| 路径/文件名错误 | 文件不存在或 `php -l` 报错 | 核对实际文件路径后修正 |
| API 路由错误 | `bin/console debug:router` 无匹配 | 用真实路由路径替换 |
| 端口/主机错误 | 连接被拒绝 | 匹配项目实际端口 |

修正必须记录在 artifact 文件头部，格式见 feature-implement SKILL.md 3e。

**重要**：同一条命令连续 2 次被修正 → 升级为 Level 1 处理。严禁借"Level 2 修正"之名降低验收标准。

### Level 3 — 逻辑性差异（🟡 备注，不暂停）

断言过宽/过严、验证范围过大。在 artifact 头部追加 `# ⚠️ VERIFICATION NOTE: <具体问题>`，任务完成后汇总报告用户。

## 3 轮修复循环

所有 implementation skill 共享的修复循环模式：

```
验证失败
    ↓
分析 artifact 中的失败输出 → 定位问题根因
    ↓
修改代码 → 重新运行质量门禁 → 重新验证（覆盖写入 artifact）
    ↓
仍失败且 < 3 轮 → 回到分析
    ↓
3 轮仍未通过 → 暂停，artifact 保留最后一轮失败日志
    ↓
向用户清晰说明：
  - 哪个验收标准失败
  - 已尝试的修复方式
  - artifact 文件路径
  - 建议的下一步
```

- 每轮修复聚焦于失败输出的具体信息，不要猜测
- 每轮修复后按顺序重新运行：质量门禁 → 验证

## Symfony 验证模式速查

| 任务类型 | 推荐验证命令 |
|----------|-------------|
| 安装依赖 | `composer show <package> --quiet` |
| 配置组件 | `php bin/console debug:config <从 debug:config --list 获取的真实 alias>` |
| 数据库 | `php bin/console doctrine:database:create` + `doctrine:schema:create --dump-sql` |
| 实体类 | `php -l src/Entity/X.php` + `doctrine:schema:validate` + `grep -q '#\[ORM\Entity' src/Entity/X.php` |
| 服务类 | `php -l src/Service/X.php` + `php bin/phpunit --filter=XTest` |
| 控制器 | `php -l src/Controller/X.php` + `debug:router \| grep "endpoint"` + `php bin/phpunit --filter=XTest` |
| 测试 | `php bin/phpunit --filter=SpecificTestName` |
| QA 全量 | `php-cs-fixer fix --dry-run` + `phpstan analyze` + `php bin/phpunit` |

**注意**：`bin/console debug:config --list` 是 Symfony config alias 的唯一真相来源。绝不猜测或编造 alias。例如 `framework.validation` 不是独立 alias，validator 配置在 `framework` 下。

**注意**：`doctrine:schema:validate` 需要数据库已存在，验证前确保数据库已创建。
