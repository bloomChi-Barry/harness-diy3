---
name: "roadmap"
description: "Records each completed iteration (feature/bug/refactor) to ROADMAP.md as a dated entry with motivation, key decisions, and artifact links. Keeps project evolution traceable."
---

# Skill Goal

将项目每次完成的迭代（feature / bug fix / refactor）记录到 `ROADMAP.md`，形成可回溯的演进时间线，辅助未来的维护决策和新人 onboarding。

---

## Role Definition

你是一名项目文档管理员，具备以下能力：

- 从 git 历史和 docs 产物中提取关键事实
- 用简洁的结构化格式记录迭代信息
- 区分"做了什么"（WHAT）、"为什么做"（WHY）、"怎么做/为什么这样选"（HOW）

---

## Input

本 skill 有两种触发方式：

1. **自动触发** — feature-implement / bug-fixer / refactor-expert 完成全部工作后自动调用
2. **手动触发** — 用户执行 `/roadmap <type> <name>`，例如 `/roadmap feat my-feature`

自动触发时，调用方会提供以下上下文：
- `type`: `feat` / `fix` / `refactor`
- `name`: 迭代名称（kebab-case）
- 已完成的工作摘要

---

## Output

- `ROADMAP.md` 中追加一条新记录
- `git commit` 提交 roadmap 变更

---

## Process Flow

```
接收迭代上下文（type / name / summary）
        ↓
读取 docs/<type>s/<name>/ 下的产物
        ↓
提取关键信息：动机、决策、涉及范围、关联提交
        ↓
生成 ROADMAP.md 条目
        ↓
插入到 ROADMAP.md 顶部（倒序，最新在前）
        ↓
git commit
```

---

## Step-by-Step Checklist

### Step 1: 收集上下文

确定以下信息：

| 字段 | 来源 | 示例 |
|------|------|------|
| `type` | 调用方提供 | `feat` / `fix` / `refactor` |
| `name` | 调用方提供 | `product-category-api` |
| `date` | 当天日期 | `2026-05-01` |
| `summary` | 调用方提供 | 一句话描述本次迭代 |
| `scope` | git diff 或调用方 | 涉及的文件/模块 |

### Step 2: 读取产物文档

根据 type 读取对应的 docs 目录：

```
feat     → docs/features/<name>/REQUIREMENT.md
fix      → docs/bugs/<name>/BUG.md
refactor → docs/refactors/<name>/ANALYSIS.md + REFACTOR_PLAN.md
```

从产物中提取：
- **动机**（WHY） — 为什么做这个迭代
- **关键决策**（HOW） — 重要的架构/设计选择及其理由
- **产物链接** — 指向详细文档的相对路径

提取原则：
- 只提取高层决策，不复制实现细节
- "选择 A 而非 B，因为 C" 的格式最有价值
- 如果产物中没有明显的决策记录，从 commit diff 中推断

### Step 3: 获取关联提交

```bash
git log --oneline --format="%h" --grep="<name>" | head -5
```

记录关联的 commit hash（短格式，用反引号包裹）。

### Step 4: 生成条目

按以下模板生成 Markdown：

```markdown
## [<date>] <type>(<name>): <summary>

**类型**: <类型标签>
**范围**: <涉及文件/模块>
**提交**: <commit_hash_1> <commit_hash_2> ...

**摘要**: <2-4 句话描述本次迭代做了什么>

**动机**: <为什么做这个迭代 — 从产物中提取>

**关键决策**:
- <决策 1> — <理由>
- <决策 2> — <理由>

**产物**: [<文档名>](<相对路径>) | [<文档名>](<相对路径>)
```

类型标签映射：
- `feat` → 新功能
- `fix` → Bug 修复
- `refactor` → 重构

### Step 5: 插入到 ROADMAP.md

ROADMAP.md 的结构：

```markdown
# 项目路线图 (Roadmap)

本文件记录项目每次迭代的内容、功能与关键决策，用于回溯演进过程、辅助维护决策。

---

## [最新日期] 最新条目
...

---

## [较早日期] 较早条目
...
```

新条目插入到第一个 `---` 分隔线之后（即紧跟在文件头下方），保持时间倒序。

具体操作：
1. 读取 `ROADMAP.md` 全文
2. 在第一个 `---` 之后、第二个 `## [` 之前插入新条目
3. 确保新条目前后各有一个空行

如果 `ROADMAP.md` 不存在，创建包含文件头和第一条记录的新文件。

### Step 6: 提交

```bash
git add ROADMAP.md
git commit -m "$(cat <<'EOF'
docs(roadmap): record <type>(<name>) - <summary>

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Entry Quality Standards

每条记录必须满足：

1. **可回溯** — 读者能理解"做了什么"和"为什么做"
2. **自包含** — 不依赖对话上下文，新成员直接读 ROADMAP 就能理解
3. **有链接** — 指向 docs/ 下的详细文档，供深入阅读
4. **关键决策 > 实现细节** — 记录选择及理由，不是代码变更列表
5. **简洁** — 摘要 2-4 句，决策列表 2-6 条

---

## Reasoning Strategy

1. **提取而非复述** — 产物文档已经很详细，ROADMAP 只提取高层摘要
2. **决策导向** — 最有长期价值的信息是"为什么选了 A 而不是 B"
3. **链接优于重复** — 详细内容通过链接指向 docs，不在 ROADMAP 中重复
4. **一致性** — 所有条目遵循相同的模板和风格

---

## Constraints

**禁止**：
- 在 ROADMAP 中复制粘贴大段代码或详细实现
- 编造不存在的决策或动机（如果产物中没有，宁可省略"关键决策"段）
- 修改已有条目的内容（只能追加新条目或修正格式）
- 删除已有条目

---

## Edge Cases

| 场景 | 处理方式 |
|------|----------|
| `ROADMAP.md` 不存在 | 创建新文件，包含文件头和第一条记录 |
| 同一 name 已有记录 | 检查是否重复，若内容相同则跳过；若不同则追加为新条目（注明"续"） |
| 产物文档不存在 | 仅从 git log 和调用方摘要生成条目，标注"产物: 无详细文档" |
| 无关键决策信息 | 省略"关键决策"段，不编造 |
| type 不是 feat/fix/refactor | 使用原始 type 值，类型标签显示为"其他" |

---

## Example Use Cases

- 自动触发：feature-implement 完成 `native-http-router` → 自动记录到 ROADMAP
- 手动触发：`/roadmap feat product-category-api` → 读取产物并生成记录
- 补充历史：`/roadmap refactor service-db-to-repository` → 从已有文档回填
