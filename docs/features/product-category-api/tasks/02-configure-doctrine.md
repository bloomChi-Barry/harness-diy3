# Configure Doctrine ORM with SQLite and Validator

## 描述
配置 DoctrineBundle 使其使用 SQLite 数据库，添加 DATABASE_URL 环境变量，注册 DoctrineBundle 到 bundles.php，创建 doctrine.yaml 配置文件定义实体路径和类型映射，创建 validator.yaml 配置文件启用验证器。

## 前置依赖
- 01-install-dependencies

## 验收标准
- [ ] config/bundles.php 中已注册 Doctrine\Bundle\DoctrineBundle\DoctrineBundle
- [ ] .env 中添加 DATABASE_URL='sqlite:///%kernel.project_dir%/var/data.db'
- [ ] config/packages/doctrine.yaml 存在，配置 dbal url 和 orm mappings (App\Entity 映射到 src/Entity, type: attribute)
- [ ] config/packages/validator.yaml 存在，启用 validation
- [ ] bin/console doctrine:database:create 成功创建 SQLite 数据库文件

## 涉及文件
- app/demo-backend-api/config/bundles.php
- app/demo-backend-api/config/packages/doctrine.yaml
- app/demo-backend-api/config/packages/validator.yaml
- app/demo-backend-api/.env

## 验证方式
```bash
cd app/demo-backend-api
php bin/console doctrine:database:create
php bin/console debug:config doctrine
```
