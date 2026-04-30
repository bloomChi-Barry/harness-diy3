# 商品分类 API - 需求说明书

## 1. 概述

### 业务目标
为电商后台管理系统提供一套 RESTful 风格的商品分类管理接口，支持树形层级结构、SEO 信息、图标和启用/禁用状态管理。

### 功能背景
运营人员需要在后台管理商品分类体系（如"服装 > 上衣 > T恤"），包括分类的创建、编辑、删除、排序、启用/禁用以及层级调整。前端通过 API 获取分类树用于展示和导航。

## 2. 目标用户

| 角色 | 描述 |
|------|------|
| 运营人员 | 通过后台管理系统维护商品分类结构 |
| 前端应用 | 通过 API 获取分类树用于商城展示 |

### 使用场景
- 运营人员在后台创建/编辑/删除商品分类
- 运营人员拖拽调整分类的层级关系和排序
- 运营人员临时禁用某个分类（不删除）
- 前端商城获取分类树用于导航和商品筛选

## 3. 功能需求

### 数据模型 — Category

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `id` | int | 自动 | 自增主键 |
| `name` | string(100) | 是 | 分类名称 |
| `parent_id` | int / null | 否 | 父级 ID，null 表示根分类 |
| `sort_order` | int | 否 | 同级排序权重，默认 0，越小越靠前 |
| `icon` | string(255) / null | 否 | 图标 URL 或图标名称 |
| `seo_title` | string(255) / null | 否 | SEO 标题 |
| `seo_description` | text / null | 否 | SEO 描述 |
| `seo_keywords` | string(255) / null | 否 | SEO 关键词 |
| `is_enabled` | bool | 否 | 是否启用，默认 true |
| `created_at` | datetime | 自动 | 创建时间 |
| `updated_at` | datetime | 自动 | 更新时间 |

### API 端点

| # | 方法 | 路径 | 说明 | 优先级 |
|---|------|------|------|--------|
| 1 | `GET` | `/api/categories` | 获取分类树 | P0 |
| 2 | `GET` | `/api/categories/{id}` | 获取单个分类详情（含子级） | P1 |
| 3 | `POST` | `/api/categories` | 创建分类 | P0 |
| 4 | `PUT` | `/api/categories/{id}` | 更新分类 | P0 |
| 5 | `DELETE` | `/api/categories/{id}` | 删除分类 | P1 |
| 6 | `PATCH` | `/api/categories/{id}/toggle` | 启用/禁用分类 | P1 |
| 7 | `PATCH` | `/api/categories/{id}/move` | 移动分类（变更父级 + 排序） | P1 |

### 接口详细说明

#### 1. GET /api/categories — 获取分类树
- 返回完整树形结构（嵌套 JSON）
- 按 `sort_order` ASC, `id` ASC 排序
- 可选 query 参数 `enabled_only=true` 仅返回启用的分类

**响应示例：**
```json
[
  {
    "id": 1,
    "name": "服装",
    "parent_id": null,
    "sort_order": 0,
    "icon": null,
    "seo_title": null,
    "seo_description": null,
    "seo_keywords": null,
    "is_enabled": true,
    "created_at": "2026-04-30T10:00:00+00:00",
    "updated_at": "2026-04-30T10:00:00+00:00",
    "children": [
      {
        "id": 2,
        "name": "上衣",
        "parent_id": 1,
        "children": []
      }
    ]
  }
]
```

#### 2. GET /api/categories/{id} — 获取分类详情
- 返回单个分类 + 直接子级列表
- 不存在时返回 404

#### 3. POST /api/categories — 创建分类
- 请求体 JSON：
```json
{
  "name": "T恤",
  "parent_id": 2,
  "sort_order": 0,
  "icon": "tshirt",
  "seo_title": "T恤 - 夏季新品",
  "seo_description": "精选各类T恤",
  "seo_keywords": "T恤,短袖,夏季",
  "is_enabled": true
}
```
- 仅 `name` 必填
- 成功返回 201 + Location 头

#### 4. PUT /api/categories/{id} — 更新分类
- 请求体同 POST，全部字段可选（部分更新）
- 不存在时返回 404
- 不能将分类的 `parent_id` 设为自己或自己的后代（防止循环引用）

#### 5. DELETE /api/categories/{id} — 删除分类
- 删除前检查是否存在子分类
- 存在子分类时返回 409 Conflict，不允许删除
- 不存在时返回 404
- 成功返回 204

#### 6. PATCH /api/categories/{id}/toggle — 启用/禁用
- 请求体：`{"is_enabled": false}`
- 切换分类的启用状态
- 不存在时返回 404

#### 7. PATCH /api/categories/{id}/move — 移动分类
- 请求体：`{"parent_id": 3, "sort_order": 5}`
- `parent_id` 为 null 表示移到根层级
- 不允许移动到自身或自身的后代
- 不存在时返回 404

### 错误响应格式

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "分类名称不能为空"
  }
}
```

## 4. 非功能需求

| 类别 | 要求 |
|------|------|
| 性能 | 分类数量 < 1000，单次请求响应时间 < 200ms |
| 安全 | 请求体验证，防止循环引用，SQL 注入防护（Doctrine 参数化查询） |
| 数据一致性 | 删除父级时检查子级存在性，移动时检查循环引用 |
| API 文档 | 自动生成 OpenAPI/Swagger 文档 |

## 5. 技术约束

| 项目 | 选型 |
|------|------|
| 语言 | PHP 8.2+ |
| 框架 | Symfony 7.2 (MicroKernel) |
| ORM | Doctrine ORM + `doctrine/doctrine-bundle` |
| 数据库 | SQLite (dev/test) |
| 测试 | PHPUnit (Symfony WebTestCase) |
| API 文档 | `nelmio/api-doc-bundle` (基于 `zircote/swagger-php` 注解) |
| CS | PHP-CS-Fixer @Symfony + PHPStan level 6 |

## 6. 验收标准

1. 7 个 API 端点全部可访问，返回正确的 HTTP 状态码和 JSON
2. 分类创建、更新、删除逻辑正确，边界条件处理完备
3. 分类树查询返回正确嵌套结构，排序正确
4. 移动分类时循环引用检测生效
5. 删除有子级的分类时返回 409
6. PHPStan level 6 通过
7. PHP-CS-Fixer 通过
8. PHPUnit 测试覆盖所有控制器端点的主要场景
9. Swagger UI 可访问，文档完整描述 7 个端点
10. `verify.sh` 验证脚本全部通过

## 7. 边界与异常

| 场景 | HTTP 状态码 | 错误码 |
|------|-------------|--------|
| 字段验证失败（name 为空） | 400 | VALIDATION_ERROR |
| 资源不存在 | 404 | NOT_FOUND |
| 删除有子级的分类 | 409 | HAS_CHILDREN |
| 移动到自己或自己的后代 | 422 | CIRCULAR_REFERENCE |
| JSON 格式错误 | 400 | INVALID_JSON |
