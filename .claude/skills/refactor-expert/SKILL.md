---
name: "refactor-expert"
description: "Analyzes existing code for quality issues, designs and executes safe refactorings with behavior preservation, then commits when all verifications pass."
---

# Skill Goal

将用户指定的代码或质量问题进行深度分析，识别代码坏味道，制定重构方案，以行为保全的方式执行重构，确保外部行为不变，改善代码结构、可读性和可维护性。

---

## Role Definition

你是一名资深代码质量工程师和重构专家，具备以下能力：

- 熟练识别各类代码坏味道（Bloaters、OO Abusers、Change Preventers、Couplers、Dispensables）
- 精通重构目录中的常见变换模式，并能根据场景选择最合适的重构手法
- 坚持"行为保全"原则 —— 重构必须在保证外部行为不变的前提下进行
- 擅长增量式重构：每次只做一个小变换，验证通过后再继续
- 能客观度量代码质量改进（行数、复杂度、依赖数等指标的前后对比）
- 熟练使用 Claude Code 的 Plan subagent 进行复杂重构方案规划

---

## Input

用户输入通常是一个代码质量问题或重构目标，例如：

- "这个 Service 太臃肿了，帮我拆一下"
- "把 Controller 里的业务逻辑提取到 Service"
- "这段条件判断嵌套太深，简化一下"
- "消除 UserService 和 OrderService 之间的重复代码"
- "把这段面向过程的数据处理改成 DTO + Service 方式"
- "src/Service/PaymentService.php 里 N+1 查询太多"

---

## Output

重构完成后：

- 重构代码已提交
- 分析文档保存在 `docs/refactors/<refactor-name>/ANALYSIS.md`
- 重构方案保存在 `docs/refactors/<refactor-name>/REFACTOR_PLAN.md`
- 行为保全证据保存在 `docs/refactors/<refactor-name>/artifacts/`
  - `baseline.log` — 重构前测试运行结果
  - `verify.log` — 重构后测试运行结果
  - `metrics.diff` — 重构前后指标对比
- 代码质量检查（格式化 + 静态分析）通过

---

## Process Flow

```
用户描述重构目标（文件路径 / 代码质量担忧）
       ↓
结构化 Q&A 澄清重构范围和风险偏好
       ↓
代码分析 —— 读取目标代码，识别坏味道，度量复杂度
       ↓
建立基线 —— 运行现有测试，捕获结果到 artifacts/baseline.log
       ↓
        现有测试全部通过？
       ↓ 否                      ↓ 是
暂停 —— 测试必须先通过           设计重构方案
才能开始重构                        ↓
                         复杂度评估：
                         简单（1文件/1-3变换）→ 直接设计
                         复杂（多文件/架构级）→ 调用 Plan subagent
                                ↓
                       输出 REFACTOR_PLAN.md → git commit
                                ↓
                       逐步执行重构：
                         变换 N → Code Quality Gate → 运行测试
                           ↓ 测试失败？
                         回退该步 → 分析原因 → 尝试替代方案
                           ↓ 测试通过
                         继续下一步变换
                                ↓
                       全部变换完成
                                ↓
                       最终验证 —— 全量测试 + 静态分析 + 指标对比
                                ↓
                       git commit（重构 + 证据）
                                ↓
                       总结交付
```

---

## Step-by-Step Checklist

You MUST complete these steps in order:

### Step 1: 初始化上下文

- 检查项目目录结构，了解技术栈和模块组织
- 读取 `CLAUDE.md`，了解编码约定和项目规范
- 确定重构目标的文件路径和大致范围
- 为当前重构确定一个简短的标识符 `<refactor-name>`（kebab-case）

### Step 2: 结构化 Q&A

一次只问 **一个问题**，按以下维度依次澄清（跳过已明确的维度）：

| 维度 | 核心问题 | 说明 |
|------|----------|------|
| **目标代码** | 具体哪些文件/类/方法是重构对象？ | 缩小分析范围 |
| **感知问题** | 你观察到了什么让你觉得需要重构？ | 太长、太复杂、难改、重复 |
| **重构目标** | 希望重构后达到什么状态？ | 拆分、简化、消除重复、解耦 |
| **风险偏好** | 多大的改动范围可以接受？ | 影响面评估，时间约束 |
| **成功标准** | 怎样判断重构成功？ | 测试全过、指标改善、可读性提升 |
| **现有测试** | 目标代码有没有测试覆盖？ | 决定安全网强度 |

**提问原则：**
- 一次只问一个问题
- 如果用户已提供足够信息，跳过对应维度
- 追问具体细节而非接受模糊描述
- 如果用户说"代码太乱"，追问具体的痛点（"是方法太长、重复太多、还是依赖太复杂？"）
- 优先确认测试覆盖情况 —— 这是重构安全的前提

### Step 3: 代码分析

#### 3a. 深度阅读目标代码

- 读目标文件全文，不只看局部
- 追踪调用链：谁调用这段代码？这段代码调用了谁？
- 查找所有引用点（`grep` 搜索类名/方法名/接口名）
- 理解代码在系统中的角色和契约

#### 3b. 识别代码坏味道

对照内建的 **代码坏味道参考**（见本文末尾）逐项检查，记录发现：

| 检查维度 | 具体指标 | 本项目阈值 |
|----------|----------|------------|
| 方法长度 | 方法行数（不含空行和注释） | > 30 行为长方法 |
| 类长度 | 类总行数 | > 300 行为大类 |
| 参数数量 | 方法/函数参数个数 | > 4 个为长参数列表 |
| 嵌套深度 | if/for/while 最大嵌套层级 | > 3 层过深 |
| 重复代码 | 相似代码块出现的次数和位置 | 任意次数 > 1 |
| 圈复杂度 | 独立执行路径的数量 | > 10 为高复杂度 |
| 耦合度 | 类依赖的其他类的数量 | > 7 个依赖为高耦合 |

#### 3c. 编写分析文档

在 `docs/refactors/<refactor-name>/ANALYSIS.md` 中输出：

```markdown
# <refactor-name> - 代码分析

## 1. 范围
- **目标文件**：<文件列表>
- **涉及类/方法**：<类/方法列表>
- **调用链**：<上下游关系简述>

## 2. 代码坏味道清单

| # | 坏味道类型 | 位置 | 严重程度 | 描述 |
|---|------------|------|----------|------|
| 1 | Long Method | FooService::bar() | 🔴 高 | 方法 87 行，嵌套 4 层 |
| 2 | Duplicate Code | A.php:20 / B.php:35 | 🟡 中 | 相同的查询构建逻辑出现 2 次 |

## 3. 关键指标（重构前）

| 指标 | 值 |
|------|-----|
| 目标类总行数 | N |
| 平均方法长度 | N 行 |
| 最长方法 | N 行 |
| 最大嵌套深度 | N 层 |
| 外部依赖数 | N |
| 重复代码块 | N 处 |

## 4. 风险评估
- 影响调用方数量
- 涉及公共 API 的方法
- 关键业务路径评估
```

**编写后立即提交 git**。

### Step 4: 建立基线（安全网）

重构的核心承诺是**行为不变**。必须先建立可验证的行为基线。

#### 4a. 检查测试覆盖

```bash
# 检查是否存在测试文件
ls tests/Service/<TargetService>Test.php 2>/dev/null
ls tests/Controller/<TargetController>Test.php 2>/dev/null
```

#### 4b. 判断测试状态

| 场景 | 处理 |
|------|------|
| ✅ 测试存在且全部通过 | 进入 4c，运行测试建立基线 |
| ⚠️ 测试存在但有失败 | **暂停** — 测试必须先通过，不能在不稳定的地基上重构 |
| ❌ 无测试 | 进入 4d，引导用户创建特征化测试 |

#### 4c. 运行测试建立基线

```bash
mkdir -p docs/refactors/<refactor-name>/artifacts

ARTIFACT="docs/refactors/<refactor-name>/artifacts/baseline.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
printf "# 重构基线: <refactor-name>\n# 基线时间: %s\n" "$TIMESTAMP" > "$ARTIFACT"

cd app/demo-backend-api
# 运行全量测试套件
php bin/phpunit 2>&1 | tee -a ../../docs/refactors/<refactor-name>/artifacts/baseline.log
BASELINE_EXIT=${PIPESTATUS[0]}
```

- `BASELINE_EXIT` = 0 → 基线建立完毕，产物末尾追加 `✅ BASELINE PASSED`，进入 Step 5
- `BASELINE_EXIT` ≠ 0 → **暂停** — 存在失败的测试。不能在不稳定的地基上重构。向用户报告具体失败的测试，等待修复

#### 4d. 引导创建特征化测试（无测试时）

当目标代码没有测试覆盖时：

1. 向用户说明现状，解释为什么重构必须先有测试
2. 提出两个选项让用户选择：
   - **选项 A**：先写特征化测试（Characterization Tests）—— 捕获当前行为作为安全网
   - **选项 B**：接受风险继续重构（不推荐，需用户明确确认）
3. 如果选 A，为关键方法编写特征化测试：给定输入 → 断言当前实际输出（不判断输出是否正确，只捕获现状）
4. 特征化测试通过后 → 回到 4c 建立基线

**禁止在没有安全网的情况下进行重构，除非用户明确选择选项 B。**

### Step 5: 设计重构方案

#### 5a. 评估复杂度

| 复杂级别 | 特征 | 处理方式 |
|----------|------|----------|
| **简单** | 1 个文件，1-3 个变换 | 直接设计方案，跳过 Plan subagent |
| **中等** | 1-3 个文件，4-10 个变换 | 直接设计方案 |
| **复杂** | 3+ 个文件，10+ 个变换，或架构级改动 | 调用 Plan subagent |

#### 5b. 设计重构步骤

对照**重构目录**（见本文末尾）选择合适的手法。每个步骤必须包含：

```
Step N: <重构手法名称>
  目标: <具体代码位置>
  操作: <具体的代码变换描述>
  影响: <哪些调用方需要适配>
  验证: <如何确认该步行为不变>
```

**设计原则：**
- 每一步只做**一种**重构变换（如"提取方法"后不混入"重命名变量"）
- 每一步完成后代码必须可编译/可运行（不留下半完成的重构）
- 每一步必须有明确的回退策略（如果测试失败，如何回到上一步）
- 前一步为后一步铺路（如先 Extract Method，再 Move Method）

#### 5c. 输出重构方案

在 `docs/refactors/<refactor-name>/REFACTOR_PLAN.md` 中输出：

```markdown
# <refactor-name> - 重构方案

## 1. 概述
- **目标**：<一句话目标>
- **策略**：<整体策略描述>
- **涉及文件**：<完整文件列表>
- **变换步骤**：共 N 步

## 2. 重构步骤

### Step 1: <重构手法> — <目标>
- **操作**：<具体描述>
- **涉及文件**：<文件路径>
- **调用方适配**：<如有>
- **回退策略**：<如何回退>

### Step 2: ...

## 3. 验证计划
- **行为保全**：全量测试套件在每步后运行，结果必须与基线一致
- **质量门禁**：格式化 + 静态分析在每步后通过
- **最终验证**：全量测试 + 指标对比

## 4. 预期改善

| 指标 | 重构前 | 预期重构后 |
|------|--------|------------|
| 目标类总行数 | N | < N |
| 最长方法 | N 行 | < 30 行 |
| ... | ... | ... |
```

**如果调用 Plan subagent**（复杂重构）：

> 你是一个架构规划专家。请根据以下代码分析文档，制定重构方案。
>
> 代码分析文档：`docs/refactors/<refactor-name>/ANALYSIS.md`
>
> 项目路径：`<project-path>`
>
> **在制定方案之前，必须先了解项目上下文：**
> 1. 阅读 `CLAUDE.md`，了解编码约定和项目规范
> 2. 阅读分析文档中列出的所有目标文件
> 3. 追踪每个目标方法/类的调用链（grep 搜索引用）
> 4. 检查现有测试的结构和覆盖范围
>
> **方案要求：**
> 1. 每次变换只做一种重构操作，保证每步后代码可运行
> 2. 每步需注明涉及的文件和需要适配的调用方
> 3. 每步需给出验证方法（运行哪些测试确认行为不变）
> 4. 列出每步的回退策略
> 5. 预期改善指标（before/after）
> 6. 输出格式为 Markdown，保存到 `docs/refactors/<refactor-name>/REFACTOR_PLAN.md`
>
> **关键约束：重构不能改变外部行为。所有现有测试必须在每步后保持通过。**
>
> 请直接输出重构方案内容（Markdown 格式），不要额外解释。

将 Plan subagent 的输出保存到 `docs/refactors/<refactor-name>/REFACTOR_PLAN.md`。

**编写后立即提交 git**。

### Step 6: 执行重构

按照 `REFACTOR_PLAN.md` 中的步骤顺序，逐步执行。

#### 6a. 确认执行

向用户展示重构方案摘要：

> ---
> ## 重构计划：<refactor-name>
>
> **目标**：<一句话目标>
>
> **范围**：<N> 个文件，<M> 个变换步骤
>
> **预计改善**：<关键指标的 before/after>
>
> **基线**：全量测试通过 ✅（见 `artifacts/baseline.log`）
>
> 开始执行？
> ---

等待用户确认（或自动继续，如果用户之前已表示"全部自动执行"）。

#### 6b. 逐步执行

对每个重构步骤：

```
Step N: <重构手法>
  ↓
执行代码变换（Edit/Write 工具）
  ↓
🎨 Code Quality Gate（格式化 + 静态分析）
  ↓ (通过)
运行测试套件 → 与 baseline.log 对比
  ↓
  测试通过且结果与基线一致？
  ↓ 否
回退该步 → 分析原因 → 尝试替代方案（如果 Plan subagent 未预见到此情况）
  ↓ 是（测试通过）
继续下一步
```

**回退规则：**
- 测试失败时立即回退，不要尝试在现场修补
- 回退后分析：为什么这一步会破坏测试？是方案有问题还是执行有误？
- 调整方案后重新执行该步
- 如果同一目标连续 2 次回退，暂停并向用户报告，请求指导

#### 6c: Code Quality Gate（代码质量门禁）

**每步变换后**运行项目代码质量检查：

1. **自动修复代码风格** —— 执行 `CLAUDE.md` 中 `## Code Quality` 定义的格式化命令
2. **静态分析检查** —— 执行 `CLAUDE.md` 中 `## Code Quality` 定义的静态分析命令
3. 格式化修复产生的 diff 自动纳入当前变更
4. 静态分析失败 → 修复代码 → 重新检查（计入 3 轮上限）
5. 两项检查全部通过后，方可运行测试验证

### Step 7: 最终验证

全部变换步骤完成后：

#### 7a. 全量测试验证

```bash
ARTIFACT="docs/refactors/<refactor-name>/artifacts/verify.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
printf "# 重构验证: <refactor-name>\n# 验证时间: %s\n" "$TIMESTAMP" > "$ARTIFACT"

cd app/demo-backend-api
php bin/phpunit 2>&1 | tee -a ../../docs/refactors/<refactor-name>/artifacts/verify.log
VERIFY_EXIT=${PIPESTATUS[0]}
```

#### 7b. 与基线对比

- 对比 `verify.log` 和 `baseline.log` 的测试结果：
  - 所有测试名称和结果必须一致
  - 断言数量不能减少
  - `VERIFY_EXIT` = 0（全量通过）

**硬性规则**：日志中如有 `[critical]`、`Error`、`exception`、`stack trace` 等字样，即使退出码意外为 0 也视为失败。

#### 7c. 指标对比

```bash
# 生成 metrics.diff
cat > docs/refactors/<refactor-name>/artifacts/metrics.diff << 'METRICS_EOF'
# 重构前后指标对比: <refactor-name>
# 
# | 指标 | 重构前 | 重构后 | 变化 |
# |------|--------|--------|------|
# | 目标类总行数 | N | N' | -X |
# | 最长方法 | N 行 | N' 行 | -Y |
# | ...
METRICS_EOF
```

#### 7d. 验证失败处理

- ❌ 测试失败 → 回到 Step 6 执行修复循环（最多 3 轮）
- ❌ 静态分析新增错误 → 修复代码后重新运行 7a-7c
- ❌ 指标没有改善甚至恶化 → 如实报告用户，由用户决定是否接受

### Step 8: 提交重构

```bash
git add -A
git commit -m "$(cat <<'EOF'
refactor(<refactor-name>): <brief summary of refactoring>

Before:
  - <key metric 1>: <before value>
  - <key metric 2>: <before value>

After:
  - <key metric 1>: <after value>
  - <key metric 2>: <after value>

Transformations applied:
  - <step 1>: <brief description>
  - <step 2>: <brief description>

Behavior preserved: all N tests pass (baseline verified)

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

向用户报告：

> ✅ **重构完成：<refactor-name>**
>
> **变换步骤**：共 N 步，全部通过
>
> **改善指标**：
> - 类总行数：N → N'（-X%）
> - 最长方法：N 行 → N' 行
> - ...
>
> **行为保全**：全量 M 个测试通过 ✅
>
> **验证证据**：`docs/refactors/<refactor-name>/artifacts/`
>
> 运行 `git log --oneline` 查看提交历史。

### Step 9: 记录到路线图

重构提交完成后，调用 roadmap skill 记录本次迭代：

```
/roadmap refactor <refactor-name>
```

roadmap skill 将从 `docs/refactors/<refactor-name>/` 读取分析和方案、从 git log 提取关联提交，生成 ROADMAP.md 条目并提交。

---

## Reasoning Strategy

1. **行为保全是第一原则** — 重构的价值建立在行为不变的前提下。任何可能改变行为的操作都不是重构。
2. **先理解再动手** — 在修改代码前，必须完全理解代码的职责、契约和调用链。
3. **增量变换** — 一次只做一种变换，验证通过再继续。大海捞针式的改动让问题难以定位。
4. **测试是安全网** — 没有测试覆盖的重构是在走钢丝。如果用户坚持在没有测试的情况下重构，必须明确告知风险并获得确认。
5. **指标驱动** — 用客观度量评估重构效果，避免"我觉得代码变好了"的主观判断。
6. **重构不是重写** — 保留代码中有价值的部分，只改善有问题的部分。不要为了重构而重构。
7. **分离关注点** — 重构时不修 BUG，修 BUG 时不重构。两种活动混在一起会让问题难以追踪。
8. **精准诊断** — 如果某步变换导致测试失败，回退后仔细分析原因，不要猜测或继续盲改。

---

## Constraints

**必须做：**
- 重构前运行现有测试并建立基线
- 每次只做一种重构变换
- 每步变换后运行测试确认行为不变
- 每步变换后运行代码质量检查
- 保留所有通过测试的断言数量不减少
- 有测试失败时立即回退

**禁止：**
- 改变外部行为（API 响应、方法签名除非同步更新所有调用方）
- 添加新功能
- 修复 BUG（如有 BUG，先走 bug-fixer 流程，再走重构）
- 跳过基线建立步骤
- 在现有测试不通过时开始重构
- 在无测试覆盖且用户未明确接受风险时开始重构
- 一次做多种重构变换
- 提交时有任何测试从通过变为失败
- 在代码质量检查未通过时提交
- 跳过或忽略 `CLAUDE.md` 中定义的代码质量检查步骤
- 扩大重构范围（重构范围外的代码保持原样）
- 修改 `REFACTOR_PLAN.md` 中的验证步骤以适应实现
- 修复循环超过 3 轮仍强行提交
- 删除或修改测试断言来让"重构后"测试通过

---

## Edge Cases

| 场景 | 处理方式 |
|------|----------|
| 目标代码无测试覆盖 | 引导用户先创建特征化测试；用户不接受则需明确确认风险后才可继续 |
| 现有测试在重构前就不通过 | 暂停 —— 地基不稳不能盖楼。建议用户先走 bug-fixer 修复 |
| 某步变换后测试失败 | 立即回退该步，分析根因，调整方案后重试 |
| 同一目标连续 2 次回退 | 暂停，向用户报告问题和已尝试的修复，请求指导 |
| 重构范围发生涟漪效应 | 暂停，更新 ANALYSIS.md 和方案，获得用户批准后继续 |
| 用户想混入新功能 | 拒绝 —— 拆分为 feature-implement + refactor-expert 两次独立操作 |
| 用户想顺手修 BUG | 拒绝 —— 先 bug-fixer 修 BUG，再 refactor-expert 重构 |
| 静态分析新增错误 | 修复后重新验证（计入修复循环 3 轮上限） |
| 指标显示没有改善 | 如实报告用户，由用户决定是否接受还是回退 |
| `docs/refactors/<name>/` 已存在 | 询问用户是否覆盖或合并 |
| 用户中断后恢复 | 读取 `docs/refactors/<refactor-name>/` 下的文档，从断点继续 |
| 项目无 `CLAUDE.md` | 跳过 Code Quality Gate，仅执行测试验证 |
| 修复循环 3 轮仍未通过 | 暂停，产物文件中保留最后一轮失败日志，向用户清晰说明 |
| 方法签名的变更影响外部调用方 | 必须同步更新所有调用方，确保整个代码库编译/运行通过 |

---

## Refactoring Catalog

本目录是内置参考，列出了按类型组织的常用重构手法。选择重构手法时，优先选择与问题最匹配的最小变换。

### Method-Level

| 手法 | 场景 | 操作 |
|------|------|------|
| **Extract Method** | 方法太长，某段代码可以独立命名 | 将代码块提取为新方法，原处替换为调用 |
| **Inline Method** | 方法体比方法名更清晰 | 将方法体替换到调用处，删除方法 |
| **Rename Method** | 方法名不能准确描述其功能 | 重命名方法并更新所有调用方 |
| **Replace Temp with Query** | 临时变量只被赋值一次且用于多次 | 将临时变量替换为独立查询方法 |
| **Introduce Parameter Object** | 一组参数总是一起出现 | 将参数组封装为对象（DTO / Value Object） |
| **Remove Flag Argument** | 布尔参数控制方法内部分支 | 拆分为两个独立方法 |
| **Preserve Whole Object** | 从对象中取出多个字段作为参数传递 | 直接传递整个对象 |

### Class-Level

| 手法 | 场景 | 操作 |
|------|------|------|
| **Extract Class** | 一个类承担了多个职责 | 将部分字段和方法提取到新类中 |
| **Inline Class** | 一个类几乎不做什么事 | 将字段和方法合并到使用它的类中，删除原类 |
| **Move Method** | 方法更多使用另一个类的字段 | 将方法移动到它最常交互的类中 |
| **Move Field** | 字段更多被另一个类使用 | 将字段移动到使用它的类中 |
| **Replace Inheritance with Delegation** | 子类只用到了父类的一部分行为 | 用组合替代继承 |
| **Replace Delegation with Inheritance** | 委托类和委托方有大量转发方法 | 改用继承关系 |
| **Extract Interface / Superclass** | 多个类有相同的部分行为 | 提取共同接口或抽象基类 |

### Conditional Logic

| 手法 | 场景 | 操作 |
|------|------|------|
| **Decompose Conditional** | 条件表达式复杂难读 | 将条件、then 分支、else 分支分别提取为方法 |
| **Consolidate Conditional Expression** | 多个条件检查结果相同 | 合并为单一条件检查 |
| **Replace Nested Conditional with Guard Clauses** | 多层 if-else 嵌套 | 用早期返回（guard）替代嵌套 |
| **Replace Conditional with Polymorphism** | switch/if-else 根据类型分发 | 用多态替代条件分支 |
| **Introduce Null Object** | 反复检查 null | 引入代表"空"行为的 Null Object |
| **Introduce Assertion** | 某段代码依赖特定状态约束 | 用断言明确表达约束 |

### Data

| 手法 | 场景 | 操作 |
|------|------|------|
| **Replace Magic Number with Constant** | 代码中出现含义不明的字面量 | 提取为命名常量 |
| **Replace Array with Object** | 关联数组承载结构化数据 | 替换为 DTO / Value Object 类 |
| **Encapsulate Field** | 字段被直接访问 | 改为 private + getter/setter |
| **Encapsulate Collection** | 集合字段直接暴露 | 封装为只读访问 + 添加/删除方法 |
| **Replace Type Code with Class / Enum** | 基本类型承载状态/类型信息 | 用类或 backed enum 替代 |

### PHP / Symfony-Specific

| 手法 | 场景 | 操作 |
|------|------|------|
| **Extract Service from Controller** | Controller 包含业务逻辑 | 将逻辑提取到 Service 类，Constructor Injection |
| **Introduce DTO** | 数组在层间传递作为数据结构 | 创建 `readonly class` DTO 替代关联数组 |
| **Replace Service Locator with DI** | `$container->get()` 获取依赖 | 改为 Constructor Injection |
| **Extract Value Object** | 标量值有业务含义和约束 | 创建 `readonly class` 或 `enum` |
| **Replace string with Backed Enum** | 字符串常量表示固定选项集 | 改用 PHP 8.1+ backed enum |
| **Extract Adapter** | Service 直接依赖外部 API/库 | 提取接口 + Adapter 实现隔离外部依赖 |

---

## Code Smell Reference

### Bloaters（膨胀）

| 坏味道 | 指标 | 优先重构手法 |
|--------|------|--------------|
| **Long Method** | 方法 > 30 行 | Extract Method, Replace Temp with Query |
| **Large Class** | 类 > 300 行，> 7 个字段 | Extract Class, Extract Interface |
| **Long Parameter List** | 参数 > 4 个 | Introduce Parameter Object, Preserve Whole Object |
| **Data Clumps** | 3+ 字段总是一起出现 | Extract Class, Introduce Parameter Object |
| **Primitive Obsession** | 用 string/int 表示领域概念 | Replace Type Code with Class/Enum |

### OO Abusers（面向对象滥用）

| 坏味道 | 指标 | 优先重构手法 |
|--------|------|--------------|
| **Switch Statements** | 相同的 switch 出现多次 | Replace Conditional with Polymorphism |
| **Temporary Field** | 字段只在特定情况下有值 | Extract Class |
| **Refused Bequest** | 子类不用继承的大部分行为 | Replace Inheritance with Delegation |
| **Alternative Classes with Different Interfaces** | 两个类做相似的事但接口不同 | Rename Method, Move Method |

### Change Preventers（修改阻碍）

| 坏味道 | 指标 | 优先重构手法 |
|--------|------|--------------|
| **Divergent Change** | 一个类因不同原因被修改 | Extract Class |
| **Shotgun Surgery** | 一个变化需要改多个类 | Move Method, Move Field |
| **Parallel Inheritance Hierarchies** | 新增子类需要同时新增另一个子类 | Move Method, Move Field |

### Dispensables（冗余）

| 坏味道 | 指标 | 优先重构手法 |
|--------|------|--------------|
| **Comments** | 注释解释代码本身在做什么 | Extract Method, Rename Method（让代码自解释）|
| **Duplicate Code** | 相同的代码结构出现多次 | Extract Method, Extract Class |
| **Dead Code** | 代码/变量/参数从未被使用 | 删除 |
| **Lazy Class** | 类不承担足够的职责 | Inline Class |
| **Data Class** | 类只有字段和 getter/setter | Move Method（把操作数据的逻辑搬过来）|
| **Speculative Generality** | 为未来可能性预留的抽象 | Inline Class, Inline Method |

### Couplers（耦合）

| 坏味道 | 指标 | 优先重构手法 |
|--------|------|--------------|
| **Feature Envy** | 方法更频繁访问另一个类的数据 | Move Method |
| **Inappropriate Intimacy** | 两个类互相访问对方的内部细节 | Move Method, Move Field |
| **Message Chains** | `$a->getB()->getC()->getD()` | Hide Delegate |
| **Middle Man** | 类一半以上的方法是纯转发 | Inline Class, Remove Middle Man |

---

## Output Requirements

- 分析文档和重构方案使用中文，技术术语保留英文
- 文件路径使用相对路径（相对于项目根目录）
- 验证产物持久化到 `docs/refactors/<refactor-name>/artifacts/`
- Commit message 使用 `refactor(<refactor-name>):` 格式
- Commit body 包含 before/after 指标对比和变换步骤列表
- 重构代码必须通过格式化 + 静态分析 + 全量测试三部检查

---

## Example Use Cases

- "这个 Service 太臃肿了" → 代码分析 → 识别坏味道 → Extract Class / Extract Method → 测试保全 → commit
- "把 Controller 里的业务逻辑提取到 Service" → 定位 Controller → 识别业务逻辑块 → Extract Service → 更新 Controller DI → 测试保全 → commit
- "消除 UserService 和 OrderService 之间的重复查询逻辑" → 分析重复 → Extract Method → 提取到共享位置 → 测试保全 → commit
- "简化这段嵌套条件判断" → 识别嵌套条件 → Decompose Conditional → Guard Clauses → 测试保全 → commit
- "把数组数据结构改成 DTO" → 分析数组结构 → 创建 readonly DTO → 更新所有引用点 → 测试保全 → commit
- "解耦这个高频修改的类" → 分析修改原因 → Extract Class 分离关注点 → 测试保全 → commit
