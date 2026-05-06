# 编写 ProductService 单元测试

## 描述
编写 `ProductServiceTest` 单元测试类，测试 `ProductService::list()` 方法的各种场景。使用 PHPUnit `TestCase`，通过 `createMock()` 模拟 `ProductRepositoryInterface`。覆盖场景：无过滤返回全部已启用商品、按分类筛选、关键词搜索、分页、空结果、limit 上限截断。

## 前置依赖
- 04: 创建 ProductService 业务逻辑层

## 验收标准
- [ ] `tests/Service/ProductServiceTest.php` 文件存在且语法正确
- [ ] 测试类继承 `PHPUnit\Framework\TestCase`
- [ ] 覆盖以下场景：
  - [ ] 无过滤条件返回所有已启用商品
  - [ ] 按 category_id 筛选
  - [ ] 按 keyword 模糊搜索
  - [ ] 分页参数正确传递
  - [ ] 空结果集
  - [ ] limit > 100 时截断为 100
  - [ ] page < 1 时设为 1
- [ ] 所有测试通过（`php bin/phpunit --filter=ProductServiceTest` 退出码 0）
- [ ] 文件以 `declare(strict_types=1);` 开头

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/tests/Service/ProductServiceTest.php`

## 验证方式

```bash
cd app/demo-backend-api && php bin/phpunit --filter=ProductServiceTest
```
