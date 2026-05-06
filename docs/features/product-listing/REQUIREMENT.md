# 商品列表接口 - 需求说明书

## 1. 概述

### 业务目标
为前端商城页面提供商品列表查询接口，支持用户按分类浏览商品、关键词搜索，以及分页加载。

### 功能背景
项目已有 Category（商品分类）实体及完整 CRUD。现需新增 Product（商品）实体，并提供列表查询接口供前端商城页面消费。

## 2. 目标用户

- **前端商城用户**：浏览商品列表，按分类筛选、按名称搜索、翻页加载

## 3. 功能需求

### P0 - 商品列表查询

**接口**：`GET /api/products`

**查询参数**：

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| `category_id` | integer | 否 | - | 按分类 ID 筛选 |
| `keyword` | string | 否 | - | 按商品名称模糊搜索 |
| `page` | integer | 否 | 1 | 页码 |
| `limit` | integer | 否 | 20 | 每页数量 |

**响应格式**（200）：

```json
{
  "data": [
    {
      "id": 1,
      "name": "商品名称",
      "price": 99.99,
      "category_id": 1,
      "is_enabled": true,
      "created_at": "2026-05-06T00:00:00+00:00",
      "updated_at": "2026-05-06T00:00:00+00:00"
    }
  ],
  "total": 100,
  "page": 1,
  "limit": 20
}
```

**默认行为**：仅返回 `is_enabled = true` 的商品。不做管理员视角的全量查询。

## 4. 非功能需求

- **性能**：支持 10000 条商品数据下，列表查询响应时间 < 200ms（单次查询含分类筛选+关键词搜索+分页）
- **安全**：遵循 PSR-12、PHPStan level 6 规范
- **兼容性**：遵循项目现有分层架构（Controller → Service → Repository → Entity）

## 5. 技术约束

- **技术栈**：PHP 8.2+ / Symfony 7.2 / Doctrine ORM
- **架构模式**：Controller 薄层 → Service 业务逻辑 → Repository 数据访问 → Entity 数据模型
- **API 文档**：使用 NelmioApiDocBundle OpenAPI 属性自动生成 Swagger 文档
- **数据存储**：MySQL，通过 Doctrine ORM 管理

## 6. 验收标准

1. `GET /api/products` 返回分页商品列表（JSON），HTTP 200
2. `?category_id=N` 筛选指定分类商品
3. `?keyword=xxx` 按名称模糊匹配
4. `?page=1&limit=10` 控制分页
5. 仅返回已启用（`is_enabled = true`）的商品
6. 数据库需先创建 Product 表（通过 Doctrine schema）
7. 代码通过 `php-cs-fixer` 和 `phpstan analyze` 检查
8. 通过 PHPUnit 测试（Service 单元测试 + Controller 集成测试）

## 7. 边界与异常

- `page` 超出实际页数时返回空 `data` 数组，`total` 仍为实际总数
- `category_id` 不存在时返回空 `data` 数组（不报 404，分类无商品是正常状态）
- `keyword` 为空字符串时忽略该参数，返回全部
- `limit` 超过 100 时截断为 100，防止大查询
- 数据库无商品时返回 `{"data": [], "total": 0, "page": 1, "limit": 20}`