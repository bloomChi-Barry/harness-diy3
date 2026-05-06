# 创建 ProductRepository 接口与实现

## 描述
创建 `ProductRepositoryInterface` 接口和 `ProductRepository` Doctrine 实现类。Repository 需要提供一个 `findByFilters` 方法，支持按 category_id 筛选、按 name 关键词模糊搜索、仅返回已启用商品、以及分页（offset/limit）查询。返回结果包含商品列表和总数。

## 前置依赖
- 01: 创建 Product 实体类

## 验收标准
- [ ] `src/Repository/ProductRepositoryInterface.php` 接口文件存在且语法正确
- [ ] `src/Repository/ProductRepository.php` 实现文件存在且语法正确
- [ ] `ProductRepository` 通过构造函数注入 `EntityManagerInterface`
- [ ] 接口包含 `findByFilters` 方法签名，参数覆盖：categoryId, keyword, page, limit, enabledOnly
- [ ] 实现类使用 Doctrine QueryBuilder 构建查询，支持多条件组合、LIKE 模糊搜索、分页
- [ ] limit 参数在实现中不做硬截断，由 Service 层负责
- [ ] 文件以 `declare(strict_types=1);` 开头

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/src/Repository/ProductRepositoryInterface.php`
- `/Users/barry/code/harness-diy3/app/demo-backend-api/src/Repository/ProductRepository.php`

## 验证方式

```bash
cd app/demo-backend-api && php -l src/Repository/ProductRepositoryInterface.php && php -l src/Repository/ProductRepository.php
```
