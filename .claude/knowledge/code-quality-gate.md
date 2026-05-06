# Code Quality Gate

所有 implementation skill（feature-implement、bug-fixer、refactor-expert）在提交代码前必须通过的质量门禁。本文档是唯一权威定义，各 skill 引用本文档而非各自描述。

## 执行顺序

每一步通过后才能进入下一步。任一步失败进入修复循环（计入对应 skill 的 3 轮上限）。

### Step 1: 自动格式化

执行 `CLAUDE.md` 中 `## Code Quality` 定义的格式化命令。格式化产生的 diff 自动纳入当前变更。

### Step 2: 静态分析

执行 `CLAUDE.md` 中 `## Code Quality` 定义的静态分析命令。

### Step 3: 注释完整性检查（仅 feature-implement）

逐文件检查本次变更：
- 每个新增类是否有类级 docblock
- 每个新增公共方法是否有 `@param` 和 `@return` 标注
- 非直观逻辑是否有行内注释解释 WHY
- DTO 每个属性是否有业务含义说明

## 失败处理

- 格式化失败：修复代码 → 重新格式化 → 重新静态分析（计入修复轮数）
- 静态分析失败：修复代码 → 重新格式化 → 重新静态分析（计入修复轮数）
- 注释缺失：补充注释 → 重新检查（计入修复轮数）

## Fallback

如果项目无 `CLAUDE.md` 或其中无 `## Code Quality` 章节：
- 跳过 Step 1 和 Step 2
- Step 3（注释完整性检查）仍然必须执行
