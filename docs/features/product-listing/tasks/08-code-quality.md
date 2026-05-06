# 代码质量检查

## 描述
运行所有代码质量工具，确保新增代码通过 PHP-CS-Fixer（PSR-12 规范）、PHPStan（level 6 静态分析）和全部 PHPUnit 测试。此任务作为最终关卡，依赖所有前序任务的代码完整。

## 前置依赖
- 01-07: 所有代码和测试均已编写完成

## 验收标准
- [ ] `vendor/bin/php-cs-fixer fix --dry-run` 退出码 0（无格式问题）
- [ ] `vendor/bin/phpstan analyze` 退出码 0（无静态分析错误，level 6）
- [ ] `php bin/phpunit` 退出码 0（全部测试通过）

## 涉及文件
- 所有新增和修改的源文件

## 验证方式

```bash
cd app/demo-backend-api && vendor/bin/php-cs-fixer fix --dry-run --allow-unsupported-php-version true && vendor/bin/phpstan analyze && php bin/phpunit
```
