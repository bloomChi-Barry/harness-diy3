# Create verify.sh verification script and run QA checks

## 描述
在 docs/features/product-category-api/ 下创建 verify.sh 验证脚本，自动执行：1) 启动 PHP 内置服务器；2) 对 7 个端点逐一执行 curl 测试并校验响应状态码和 JSON 结构；3) 清理测试数据；4) 停止服务器。随后执行 QA：运行 php-cs-fixer fix (dry-run) 检查代码风格，运行 phpstan analyze 检查静态分析，运行 phpunit 完整测试套件，修复所有发现的代码规范问题。

## 前置依赖
- 08-configure-api-docs
- 10-controller-tests

## 验收标准
- [ ] docs/features/product-category-api/verify.sh 存在且可执行
- [ ] verify.sh 自动启动/停止 PHP 内置服务器
- [ ] verify.sh 覆盖全部 7 个端点的 curl 调用，校验 HTTP 状态码
- [ ] verify.sh 对创建/更新/删除/移动/切换操作均有 curl 测试覆盖
- [ ] vendor/bin/php-cs-fixer fix --allow-unsupported-php-version true --dry-run 通过 (exit code 0)
- [ ] vendor/bin/phpstan analyze 通过 (exit code 0, level 6, 无错误)
- [ ] php bin/phpunit 全部测试通过 (exit code 0)
- [ ] bin/console cache:clear 无异常

## 涉及文件
- docs/features/product-category-api/verify.sh

## 验证方式
```bash
bash docs/features/product-category-api/verify.sh
```
