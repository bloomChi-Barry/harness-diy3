# 创建 ProductService 业务逻辑层

## 描述
创建 `ProductService` 服务类，包含 `list()` 方法实现商品列表查询的业务逻辑。支持按 category_id 筛选、关键词模糊搜索、分页。对 limit 参数做硬上限截断（> 100 时截为 100）。仅返回 is_enabled = true 的商品。服务依赖 `ProductRepositoryInterface` 和 `ProductOutput` DTO 进行数据转换。

## 前置依赖
- 01: 创建 Product 实体类
- 02: 创建 ProductOutput DTO
- 03: 创建 ProductRepository 接口与实现

## 验收标准
- [ ] `src/Service/ProductService.php` 文件存在且语法正确
- [ ] 通过构造函数注入 `ProductRepositoryInterface`
- [ ] `list()` 方法接受参数：`?int $categoryId`, `?string $keyword`, `int $page`, `int $limit`
- [ ] 返回值格式：`['data' => array, 'total' => int, 'page' => int, 'limit' => int]`
- [ ] limit > 100 时自动截断为 100
- [ ] page < 1 时自动设为 1
- [ ] keyword 为空字符串或 null 时忽略该过滤条件
- [ ] 始终过滤 is_enabled = true
- [ ] 文件以 `declare(strict_types=1);` 开头

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/src/Service/ProductService.php`

## 验证方式

```bash
cd app/demo-backend-api && php -l src/Service/ProductService.php
```
