# 项目路线图 (Roadmap)

本文件记录项目每次迭代的内容、功能与关键决策，用于回溯演进过程、辅助维护决策。

---

## [2026-05-01] refactor(skills-workflow-improvement): 工作流工具链四项定向改进

**类型**: 流程改进
**范围**: `.claude/skills/*` / `CLAUDE.md` / `.claude/knowledge/`（新）
**提交**: `a4e3bdc`

**摘要**: 基于对当前工作流的系统评价，实施了四项定向改进：消除 CLAUDE.md 与实际技术栈的不一致；简化 feature-analyzer 的 Plan→JSON→Python 生成链为 Plan→Markdown 直接输出；在 feature-implement 中引入验证命令三级修正协议替代僵化的"合约不可变"规则；创建跨技能知识库实现技能间经验反馈。

**动机**: 工作流评审发现四个结构性缺陷——(1) CLAUDE.md 声称"无 Doctrine"但实际使用 Doctrine ORM，导致所有 skill 读取错误上下文；(2) feature-analyzer 的任务生成链（Plan subagent → JSON → Python 脚本）有三个故障点且难以调试；(3) 验证命令作为"不可变合约"的理念导致僵局——当 analyzer 写错路径或 alias 时 implement 被卡住；(4) 五个 skill 各自孤立运行，feature-implement 发现的验证坑不会反馈给 feature-analyzer。

**关键决策**:
- 文档同步采用"描述实际状态"原则，不保留历史模板痕迹 — 避免漂移复现
- Plan subagent 输出 fenced code blocks 而非 JSON — Claude Code 原生支持 Markdown 解析，无需中间格式
- 三级分类替代一刀切"禁止修改" — 反模式仍需暂停（L1），事实性错误允许带审计追踪的修正（L2），逻辑差异备注后继续（L3），平衡了合约严肃性与实用性
- 知识库使用简单 Markdown 文件而非数据库或结构化 schema — 最小化维护成本，Claude Code 可直接读取
- 知识库种子条目从项目实际历史中提取 — 空知识库无法产生正反馈循环

**产物**: 无独立 docs 目录（本迭代是对工具链本身的改进，产物为上述文件变更）

---

## [2026-05-01] feat: 完成整个工具链

**类型**: 功能增强
**范围**: `.claude/skills/feature-implement/`
**提交**: `0f4aea5`

**摘要**: 完善 feature-implement skill，补充代码注释标准（类、公共方法、非直观逻辑必须有注释），增加注释完整性自查作为 Code Quality Gate 的一部分，完成 4-skill 工具链（feature-analyzer → feature-implement → bug-fixer → refactor-expert）的首个可用版本。

**关键决策**:
- 注释标准作为强制门禁，不计入修复循环的独立步骤
- 拆分/追加任务机制允许实现过程中动态调整计划

---

## [2026-05-01] refactor(service-db-to-repository): Service 与持久化层解耦

**类型**: 重构
**范围**: `CategoryService` / `CategoryRepository` / `CategoryRepositoryInterface`
**提交**: `09b006b` `d6929b5` `c3fa848`

**摘要**: 将 `CategoryService` 从直接依赖 `EntityManagerInterface` 重构为依赖 `CategoryRepositoryInterface`，同时修复两处 N+1 查询问题。引入 Repository 模式建立数据访问抽象层。

**动机**: CategoryService（237 行）承担了业务逻辑、数据访问、树构建、验证 4 种职责。直接依赖 Doctrine EntityManager 导致 Service 无法脱离数据库做单元测试，且存在 N+1 查询。

**关键决策**:
- Repository 只暴露 `save()` + `remove()`，不暴露 `persist()`/`flush()` — Service 不应感知 Doctrine UnitOfWork 状态
- Repository Interface 不暴露 `getEntityManager()` — 暴露即等于没有抽象
- 树构建保留在 Service 层 — 涉及 DTO 转换和 enabledOnly 过滤，属于业务逻辑
- 通过 `findAllOrdered()` 一次性获取所有 Category，在 Service 中用 adjacency map 构建树，消除 N+1

**产物**: [ANALYSIS.md](docs/refactors/service-db-to-repository/ANALYSIS.md) | [REFACTOR_PLAN.md](docs/refactors/service-db-to-repository/REFACTOR_PLAN.md)

---

## [2026-05-01] fix(api-doc-404): PHP 内置服务器 .json 路由修复

**类型**: Bug 修复
**范围**: `public/router.php` / `CLAUDE.md`
**提交**: `cdc95e2` `16d5c41`

**摘要**: 修复 Swagger UI 在 PHP 内置服务器下无法加载 API 文档的问题。根因是 PHP 内置服务器对含扩展名（`.json`）的 URI 视为静态文件请求，文件不存在时直接返回 404，不执行 Symfony 前端控制器回退。

**根因**: PHP 内置服务器路由策略 — 含扩展名的 URI 不触发 `index.php` 回退，导致 `/api/doc.json` 绕过 Symfony Router 直接 404。

**修复方案**: 创建 `public/router.php` 作为 PHP 内置服务器的入口路由脚本，使所有请求经过 `public/index.php`（Symfony 前端控制器）。

**产物**: [BUG.md](docs/bugs/api-doc-404/BUG.md)

---

## [2026-04-30] feat(product-category-api): 商品分类 REST API

**类型**: 新功能
**范围**: `Category` 实体 / `CategoryService` / `CategoryController` / `CategoryRepository` / DTO / 异常 / 测试
**提交**: `657f6aa` → `6525497`（共 11 个任务 + 2 个文档提交）

**摘要**: 为电商后台管理系统提供完整的 RESTful 商品分类管理接口，支持树形层级结构、SEO 信息、图标和启用/禁用状态管理。这是项目的首个完整功能，用于验证 4-skill 工具链可行性。

**数据模型**: Category 实体 11 个字段（id, name, parent, children, sort_order, icon, seo_*×3, is_enabled, created_at, updated_at），自引用树形结构。

**API 端点**（7 个）:

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/categories` | 获取分类树（支持 `?enabled_only=true`） |
| GET | `/api/categories/{id}` | 获取单个分类详情 + 直接子项 |
| POST | `/api/categories` | 创建分类 |
| PUT | `/api/categories/{id}` | 部分更新（含循环引用保护） |
| DELETE | `/api/categories/{id}` | 删除分类（有子项返回 409） |
| PATCH | `/api/categories/{id}/toggle` | 启用/禁用切换 |
| PATCH | `/api/categories/{id}/move` | 移动分类（变更父级 + 排序） |

**技术栈**: Symfony 7.2 + Doctrine ORM 3 + SQLite + NelmioApiDocBundle (Swagger)

**关键决策**:
- DTO 使用 `readonly class` + 三种工厂方法（`fromEntity` / `fromEntityWithoutChildren` / `fromEntityShallow`）控制序列化深度，防止循环引用
- 异常通过 `ExceptionListener` 统一映射到 JSON 响应
- 测试覆盖 14 个（5 单元 + 9 集成）

**产物**: [REQUIREMENT.md](docs/features/product-category-api/REQUIREMENT.md) | [任务拆解](docs/features/product-category-api/tasks/) | [verify.sh](docs/features/product-category-api/verify.sh)

---

> **格式说明**: 条目按时间倒序排列。每条记录包含类型、范围、关联提交、摘要、动机（为何做）、关键决策（为何这样做）、产物链接（指向详细文档）。
