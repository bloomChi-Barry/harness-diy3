# Install project dependencies

## 描述
使用 Composer 安装生产依赖 (doctrine/doctrine-bundle, doctrine/orm, nelmio/api-doc-bundle, symfony/validator) 和开发依赖 (phpunit/phpunit, symfony/phpunit-bridge)。由于项目使用 HTTP 代理 127.0.0.1:6244，需通过 HTTP_PROXY/HTTPS_PROXY 环境变量执行。

## 前置依赖
-

## 验收标准
- [ ] composer.json 中 require 段包含 doctrine/doctrine-bundle, doctrine/orm, nelmio/api-doc-bundle, symfony/validator
- [ ] composer.json 中 require-dev 段包含 phpunit/phpunit, symfony/phpunit-bridge
- [ ] composer install 成功，无错误输出
- [ ] vendor/bin/phpunit --version 可正常执行

## 涉及文件
- app/demo-backend-api/composer.json
- app/demo-backend-api/composer.lock

## 验证方式
```bash
cd app/demo-backend-api
HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 composer install
vendor/bin/phpunit --version
```
