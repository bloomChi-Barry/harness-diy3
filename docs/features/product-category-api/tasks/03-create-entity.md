# Create Category Entity

## 描述
创建 src/Entity/Category.php 实体类，包含需求文档定义的全部 10 个字段 (id, name, parent_id, sort_order, icon, seo_title, seo_description, seo_keywords, is_enabled, created_at, updated_at)。使用 Doctrine ORM Attribute 映射，支持自引用 ManyToOne/OneToMany 关系（parent/children）。通过 HasLifecycleCallbacks 自动管理 created_at/updated_at。运行 doctrine:schema:create 生成数据库表。

## 前置依赖
- 02-configure-doctrine

## 验收标准
- [ ] src/Entity/Category.php 包含所有字段，使用 Doctrine ORM Attribute 映射
- [ ] parent 属性为 ManyToOne 自引用 (nullable)，children 为 OneToMany 反向映射
- [ ] sort_order 默认值为 0，is_enabled 默认值为 true
- [ ] created_at/updated_at 通过 #[PrePersist] / #[PreUpdate] 生命周期回调自动设置
- [ ] bin/console doctrine:schema:validate 通过 (无映射错误)
- [ ] bin/console doctrine:schema:create --dump-sql 输出正确的 CREATE TABLE SQL

## 涉及文件
- app/demo-backend-api/src/Entity/Category.php

## 验证方式
```bash
cd app/demo-backend-api
php bin/console doctrine:schema:validate
php bin/console doctrine:schema:create --dump-sql
```
