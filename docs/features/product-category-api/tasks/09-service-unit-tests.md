# Set up PHPUnit test infrastructure and CategoryService unit tests

## 描述
创建 phpunit.xml.dist 配置文件（设置 APP_ENV=test, DATABASE_URL 使用 SQLite 内存数据库），创建 tests/bootstrap.php 引导文件。创建 tests/Service/CategoryServiceTest.php，使用 PHPUnit Mock 模拟 EntityManagerInterface 和 CategoryRepository，对 CategoryService 的核心方法进行单元测试：getTree 树形结构正确性、getById 存在/不存在、create 成功/name 为空、update 循环引用检测、delete 有子级抛异常/无子级成功、toggle 切换、move 循环引用检测。

## 前置依赖
- 05-create-service

## 验收标准
- [ ] phpunit.xml.dist 存在，配置 APP_ENV=test, DATABASE_URL=sqlite:///:memory:
- [ ] tests/bootstrap.php 存在，加载 autoload 和 .env
- [ ] tests/Service/CategoryServiceTest.php 包含至少 10 个测试方法
- [ ] 测试覆盖 getTree (含 enabled_only 过滤)、getById (成功+404)、create (成功+验证失败)
- [ ] 测试覆盖 update (循环引用检测)、delete (有子级→409, 无子级→成功)
- [ ] 测试覆盖 toggle (切换状态)、move (正常移动+循环引用检测)
- [ ] php bin/phpunit --filter=CategoryServiceTest 全部通过

## 涉及文件
- app/demo-backend-api/phpunit.xml.dist
- app/demo-backend-api/tests/bootstrap.php
- app/demo-backend-api/tests/Service/CategoryServiceTest.php

## 验证方式
```bash
cd app/demo-backend-api
php bin/phpunit --filter=CategoryServiceTest
```
