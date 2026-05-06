# 创建 Product 实体类

## 描述
创建 `Product` Doctrine ORM 实体类，包含字段：id (自增主键)、name (商品名称)、price (价格，decimal 类型)、category (多对一关联到 Category 实体)、isEnabled (启用状态，默认 true)、createdAt (创建时间)、updatedAt (更新时间)。使用 PHP 8 属性进行 ORM 映射，并包含生命周期回调自动设置时间戳。

## 前置依赖
- 无

## 验收标准
- [ ] `src/Entity/Product.php` 文件存在且语法正确
- [ ] 实体包含所有必需字段：id, name, price, category, isEnabled, createdAt, updatedAt
- [ ] 使用 `#[ORM\Entity]`、`#[ORM\Table]`、`#[ORM\HasLifecycleCallbacks]` 属性
- [ ] 包含 `#[ORM\PrePersist]` 和 `#[ORM\PreUpdate]` 生命周期回调
- [ ] 所有属性有对应的 getter/setter 方法，遵循 PSR-12 和项目命名规范
- [ ] 文件以 `declare(strict_types=1);` 开头

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/src/Entity/Product.php`

## 验证方式

```bash
cd app/demo-backend-api && php -l src/Entity/Product.php
```
