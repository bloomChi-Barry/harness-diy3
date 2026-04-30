# Create PHPUnit controller integration tests (WebTestCase)

## 描述
创建 tests/Controller/CategoryControllerTest.php，继承 Symfony\Bundle\FrameworkBundle\Test\WebTestCase。使用 SQLite 内存数据库，在 setUp 中创建 schema 并插入种子数据。测试覆盖全部 7 个端点的正常流程和异常场景：GET 树正常/过滤、GET 详情正常/404、POST 创建正常/缺少 name→400、PUT 更新正常/404/循环引用→422、DELETE 正常/有子级→409/404、PATCH toggle 正常/404、PATCH move 正常/404/循环引用→422。对 JSON 响应进行结构和状态码断言。

## 前置依赖
- 07-create-controller
- 09-service-unit-tests

## 验收标准
- [ ] tests/Controller/CategoryControllerTest.php 包含至少 15 个测试方法
- [ ] 测试方法命名遵循 test{Method}{Scenario} 模式 (如 testGetCategoriesReturnsTree)
- [ ] setUp 中初始化 SQLite 内存数据库 schema 和种子数据，tearDown 中清理
- [ ] 每个测试方法通过 $client->request() 发起请求，assertResponseStatusCodeSame() 验证状态码
- [ ] 测试覆盖 GET /api/categories (200, 含嵌套 children 结构校验)
- [ ] 测试覆盖 GET /api/categories?enabled_only=true (过滤已禁用分类)
- [ ] 测试覆盖 GET /api/categories/{id} (200 + JSON 结构校验 / 404)
- [ ] 测试覆盖 POST /api/categories (201 + Location / 400 validation error)
- [ ] 测试覆盖 PUT /api/categories/{id} (200 / 404 / 422 circular reference)
- [ ] 测试覆盖 DELETE /api/categories/{id} (204 / 409 has children / 404)
- [ ] 测试覆盖 PATCH /api/categories/{id}/toggle (200 / 404)
- [ ] 测试覆盖 PATCH /api/categories/{id}/move (200 / 404 / 422 circular reference)
- [ ] php bin/phpunit --filter=CategoryControllerTest 全部通过

## 涉及文件
- app/demo-backend-api/tests/Controller/CategoryControllerTest.php

## 验证方式
```bash
cd app/demo-backend-api
php bin/phpunit --filter=CategoryControllerTest
```
