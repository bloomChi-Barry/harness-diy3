# service-db-to-repository - 代码分析

## 1. 范围

- **目标文件**：`src/Service/CategoryService.php`
- **涉及类/方法**：
  - `CategoryService` — 全部方法（getTree, getById, create, update, delete, toggle, move）
  - `CategoryController` — 适配层（调用方）
  - `CategoryServiceTest` — 单元测试适配
- **新增文件**：
  - `src/Repository/CategoryRepositoryInterface.php`
  - `src/Repository/CategoryRepository.php`
- **调用链**：
  ```
  HTTP Request → CategoryController → CategoryService → EntityManagerInterface
                                                       → EntityRepository (Doctrine default)
  ```
  CategoryService 同时处理 HTTP 请求编排、业务规则验证、DTO 转换、树构建、以及所有持久化操作。

## 2. 代码坏味道清单

| # | 坏味道类型 | 位置 | 严重程度 | 描述 |
|---|------------|------|----------|------|
| 1 | **Large Class** | `CategoryService` (237行) | 🟡 中 | 单个类承担了业务逻辑、数据访问、树构建、验证 4 种职责 |
| 2 | **Feature Envy** | `CategoryService::findCategory()` (line 158-166) | 🔴 高 | 方法直接操作 `EntityManagerInterface::getRepository()`，应委托给 Repository |
| 3 | **Feature Envy** | `CategoryService::getAncestorIds()` (line 183-196) | 🔴 高 | 直接在 Service 中遍历 ORM 对象图获取祖先链，且存在 N+1 查询 |
| 4 | **Feature Envy** | `CategoryService::getTree()` (line 25-31) | 🟡 中 | 直接调用 `getRepository()->findBy()` 获取数据 |
| 5 | **Primitive Obsession / No Repository** | 整个 `CategoryService` | 🔴 高 | 项目完全没有 Repository 层，所有持久化操作都绕过抽象直接使用 `EntityManagerInterface` |
| 6 | **N+1 Query** | `CategoryService::getAncestorIds()` (line 183-196) | 🟡 中 | 循环中逐级 `find()` 父节点，深度为 N 时产生 N 次查询 |
| 7 | **N+1 Query** | `CategoryService::buildNode()` (line 222-236) | 🟡 中 | 每个节点的 `getChildren()` 触发懒加载，树节点数为 N 时产生 N+1 次查询 |
| 8 | **Tight Coupling** | `CategoryService` → `EntityManagerInterface` | 🔴 高 | Service 直接依赖 Doctrine 基础设施，无法脱离数据库进行单元测试（当前测试靠 Mock EM 绕过） |

## 3. 关键指标（重构前）

| 指标 | 值 |
|------|-----|
| 目标类总行数 | 237 |
| 公共方法数 | 7 |
| 私有方法数 | 4 |
| 最长方法 | `create()` — 37 行 |
| 最大嵌套深度 | 3 层（`buildNode` 中 foreach → if → recursive call） |
| 外部依赖数 | 6（EntityManagerInterface, Category, CategoryInput, CategoryOutput, CategoryNotFoundException, CategoryHasChildrenException, CircularReferenceException） |
| 直接 EM 访问点 | 9 处（getTree, findCategory, create×2, update, delete×2, toggle, getAncestorIds） |
| Repository 类 | 0（不存在） |
| Repository Interface | 0（不存在） |

## 4. 数据访问分布

| 方法 | 数据访问操作 | 次数 |
|------|-------------|------|
| `getTree()` | `getRepository()->findBy()` | 1 |
| `getById()` | `findCategory()` → `find()` | 1 |
| `create()` | `findCategory()` + `persist()` + `flush()` | 3 |
| `update()` | `findCategory()` × 2 + `flush()` | 3 |
| `delete()` | `findCategory()` + `remove()` + `flush()` | 3 |
| `toggle()` | `findCategory()` + `flush()` | 2 |
| `move()` | `findCategory()` × 2 + `flush()` | 3 |
| `findCategory()` | `getRepository()->find()` | 被调用 6 次 |
| `getAncestorIds()` | `getRepository()->find()` 循环 | N 次 |

## 5. 风险评估

- **调用方**：`CategoryController` 所有 7 个 action 方法都调用 `CategoryService`，但 Controller 只依赖 Service 接口，不感知内部实现变化
- **涉及公共 API**：`CategoryService` 的 7 个公共方法签名在重构中保持不变
- **关键业务路径**：CRUD + 树结构 + 移动 + 启用/禁用，全部覆盖在集成测试中
- **测试安全**：14 个 Category 相关测试（5 单元 + 9 集成），可以作为重构安全网
