# CategoryService 重构计划：Service-DB 分层解耦 (Option C — Architectural)

## 1. 概述

### 1.1 目标

将 `CategoryService` 从直接依赖 `EntityManagerInterface` 重构为依赖 `CategoryRepositoryInterface`，实现 Service 层与持久化层的彻底解耦。同时修复两处 N+1 查询问题。

### 1.2 策略

采用 **Introduce Repository + Parameterize Constructor** 重构策略，分 4 个步骤执行：

1. 创建 `CategoryRepositoryInterface` — 定义数据访问契约
2. 创建 `CategoryRepository` (Doctrine 实现) — 实现所有持久化逻辑
3. 改造 `CategoryService` + 单元测试适配 — 替换依赖并修复 N+1
4. 验证与清理 — 全量测试、静态分析、代码格式化

### 1.3 涉及文件

| 文件 | 角色 | 变更类型 |
|------|------|----------|
| `src/Repository/CategoryRepositoryInterface.php` | 数据访问契约 | **新建** |
| `src/Repository/CategoryRepository.php` | Doctrine 持久化实现 | **新建** |
| `src/Service/CategoryService.php` | 业务逻辑 | **修改** |
| `src/Dto/CategoryOutput.php` | DTO 工厂方法 | **修改** |
| `tests/Service/CategoryServiceTest.php` | 单元测试 | **修改** |
| `config/services.yaml` | DI 配置 | 可能微调 |
| `src/Controller/CategoryController.php` | HTTP 层 | **不修改** |

### 1.4 步骤总数

共 **4 步**

---

## 2. 关键架构决策

### 决策 1：persist()/flush()/remove() vs 单一 save() 方法？

**选择：`save(Category $category): void` + `remove(Category $category): void`**

理由：
- `EntityManager::persist()` 对已 managed entity 是 no-op，因此 `save()` = `persist() + flush()` 对新建和更新均安全
- Service 不应感知 Doctrine UnitOfWork 状态（managed vs new），这是持久化层的实现细节
- 单独的 `remove()` 语义清晰，与"保存"明显区分

### 决策 2：Repository Interface 是否暴露 getEntityManager()？

**选择：不暴露。**

理由：暴露 EM 等于没有抽象，Service 依然与 Doctrine 耦合。

### 决策 3：Tree Building 放在 Repository 还是 Service？

**选择：放在 Service 层（PHP-level），Repository 只负责获取原始数据。**

理由：
- Repository 是纯数据访问层，职责单一
- 树构建涉及 enabledOnly 过滤、DTO 转换，属于业务逻辑
- 通过 `findAllOrdered()` 一次性获取所有 Category，在 Service 中用 adjacency map 构建树，消除 N+1

### 决策 4：getAncestorIds() 的 N+1 修复方案

**选择：Repository 内部使用 DQL 标量查询直接获取 parent_id 映射，一次查询 + PHP 内存遍历。**

```sql
SELECT c.id, IDENTITY(c.parent) FROM App\Entity\Category c
```

一次性获取所有 id → parent_id 映射，然后在 PHP 中沿着映射向上遍历。

### 决策 5：buildTree() 的 N+1 修复方案

**选择：基于 `findAllOrdered()` 返回的扁平列表构建 `parentId => Category[]` adjacency map，用 map 递归构建树，永不调用 `$category->getChildren()`。**

在 `CategoryOutput` 中新增 `fromEntityWithoutChildren()` 工厂方法，替代原有的 `fromEntity()`（后者内部调用 `getChildren()` 触发懒加载）。

---

## 3. 分步执行计划

### Step 1：创建 CategoryRepositoryInterface（数据访问契约）

**重构技术**：Extract Interface

新建 `src/Repository/CategoryRepositoryInterface.php`，定义 5 个方法：

- `findById(int $id): ?Category`
- `findAllOrdered(): array`
- `findAncestorIds(int $categoryId): array`
- `save(Category $category): void`
- `remove(Category $category): void`

**验证**：`php bin/phpunit`（无行为变更，仅新增文件）

### Step 2：创建 CategoryRepository（Doctrine 实现）

**重构技术**：Introduce Repository Implementation

新建 `src/Repository/CategoryRepository.php`，实现 `CategoryRepositoryInterface`：

- `findById()` → `$em->getRepository(Category::class)->find($id)`
- `findAllOrdered()` → `$em->getRepository(Category::class)->findBy([], [...])`
- `findAncestorIds()` → DQL 标量查询获取全量 id→parentId 映射，PHP 遍历
- `save()` → `$em->persist() + $em->flush()`
- `remove()` → `$em->remove() + $em->flush()`

**验证**：
- `php bin/console debug:container App\\Repository\\CategoryRepository` — service 存在
- `php bin/phpunit` — 全部通过

### Step 3：改造 CategoryService + 单元测试 + N+1 修复

**重构技术**：Parameterize Constructor + Replace Data Access

#### 3a. 修改构造函数
- `EntityManagerInterface` → `CategoryRepositoryInterface`
- 属性名 `$entityManager` → `$repository`

#### 3b. 替换 9 处数据访问调用

| 位置 | 原代码 | 新代码 |
|------|--------|--------|
| `getTree()` L27-28 | `$em->getRepository()->findBy(...)` | `$repo->findAllOrdered()` |
| `findCategory()` L160 | `$em->getRepository()->find($id)` | `$repo->findById($id)` |
| `create()` L75-76 | `$em->persist()` + `$em->flush()` | `$repo->save($category)` |
| `update()` L114 | `$em->flush()` | `$repo->save($category)` |
| `delete()` L127-128 | `$em->remove()` + `$em->flush()` | `$repo->remove($category)` |
| `toggle()` L135 | `$em->flush()` | `$repo->save($category)` |
| `move()` L153 | `$em->flush()` | `$repo->save($category)` |
| `getAncestorIds()` L186-187 | `$em->getRepository()->find()` 循环 | `$repo->findAncestorIds($id)` |

#### 3c. 修复 N+1 查询

**N+1 #1 — getAncestorIds()**：委托给 `$repo->findAncestorIds()`，Repository 内部一条 DQL 获取全量 parent 映射，PHP 遍历。

**N+1 #2 — buildTree()**：
- 用 `findAllOrdered()` 返回的扁平列表构建 `parentId => Category[]` adjacency map
- 新增 `buildTreeFromMap()` 方法替代 `buildTree()` + `buildNode()`
- 在 `CategoryOutput` 中新增 `fromEntityWithoutChildren()` 工厂方法
- 树构建变为纯内存操作，O(n) 复杂度

#### 3d. 更新单元测试
- Mock `CategoryRepositoryInterface` 替代 `EntityManagerInterface` + `EntityRepository`
- `save()` Mock 验证替代 `persist()` + `flush()` Mock 验证
- Circular reference 测试需要显式 Mock `findAncestorIds()`

**验证**：`php bin/phpunit` + `vendor/bin/phpstan analyze` + `vendor/bin/php-cs-fixer fix`

### Step 4：最终验证与 DI 确认

- 确认 Symfony autowiring 自动绑定 Interface → Implementation
- 如需显式 alias，在 `config/services.yaml` 添加
- 全量 39 tests / 120 assertions 通过
- 代码质量检查通过

---

## 4. 验证计划

| 步骤 | 验证命令 | 预期 |
|------|----------|------|
| Step 1 | `php bin/phpunit` + `phpstan` + `php-cs-fixer` | 全通过 |
| Step 2 | `php bin/phpunit` + `debug:container` | 全通过 |
| Step 3 | `php bin/phpunit` + `phpstan` + `php-cs-fixer` | 全通过 |
| Step 4 | `php bin/phpunit` | 39/39 通过 |

## 5. 预期改善

| 指标 | 重构前 | 重构后 | 变化 |
|------|--------|--------|------|
| `CategoryService` 行数 | 237 | ~190 | -20% |
| 直接 EM 访问点 | 9 处 | 0 处 | -100% |
| `src/Repository/` 文件数 | 0 | 2 | +2 |
| Service 依赖数 | 6 | 5 | -1 |
| `getAncestorIds()` 查询数 (depth=3) | 4 次 | 1 次 | -75% |
| `buildTree()` 查询数 (10 nodes) | 11 次 | 1 次 | -91% |
| 单元测试 Mock 对象 | 2 | 1 | -1 |
| Controller 改动 | — | 0 | 无 |
| DTO 改动 | — | 1 方法新增 | 最小 |
