# 编写 ProductController 集成测试

## 描述
编写 `ProductControllerTest` 集成测试类，测试 `GET /api/products` 端点。使用 `WebTestCase` 启动 Symfony 内核，通过 SchemaTool 在 SQLite 内存数据库中创建表结构并填充测试数据。覆盖场景：基本列表查询、分类筛选、关键词搜索、分页、边界情况（空结果、超出页数、大 limit 截断）。

## 前置依赖
- 05: 创建 ProductController API 端点

## 验收标准
- [ ] `tests/Controller/ProductControllerTest.php` 文件存在且语法正确
- [ ] 测试类继承 `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`
- [ ] setUp 中使用 SchemaTool 创建 schema 并填充测试 Product 数据（关联 Category）
- [ ] tearDown 中清理 schema
- [ ] 覆盖以下场景：
  - [ ] 基本 GET /api/products 返回分页列表，仅包含已启用商品
  - [ ] ?category_id=N 筛选
  - [ ] ?keyword=xxx 搜索
  - [ ] ?page=1&limit=10 分页
  - [ ] 空结果返回正确结构
  - [ ] 仅返回 is_enabled = true 的商品
  - [ ] limit > 100 被截断
- [ ] 所有测试通过（`php bin/phpunit --filter=ProductControllerTest` 退出码 0）
- [ ] 文件以 `declare(strict_types=1);` 开头

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/tests/Controller/ProductControllerTest.php`

## 验证方式

```bash
cd app/demo-backend-api && php bin/phpunit --filter=ProductControllerTest
```
