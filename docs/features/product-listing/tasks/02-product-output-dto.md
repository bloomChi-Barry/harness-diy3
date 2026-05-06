# 创建 ProductOutput DTO

## 描述
创建 `ProductOutput` 只读 DTO 类，用于将 Product 实体转换为 API 响应数组。遵循项目现有的 DTO 模式（`readonly class`、私有构造函数、静态工厂方法 `fromEntity()`、`toArray()` 方法）。输出字段需匹配需求文档中的 JSON 响应格式。

## 前置依赖
- 01: 创建 Product 实体类

## 验收标准
- [ ] `src/Dto/ProductOutput.php` 文件存在且语法正确
- [ ] 类是 `readonly class`，构造函数为私有
- [ ] 包含 `fromEntity(Product): self` 静态工厂方法
- [ ] `toArray()` 返回数组包含字段：id, name, price, category_id, is_enabled, created_at, updated_at
- [ ] 时间字段使用 `\DateTimeInterface::ATOM` 格式
- [ ] 文件以 `declare(strict_types=1);` 开头

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/src/Dto/ProductOutput.php`

## 验证方式

```bash
cd app/demo-backend-api && php -l src/Dto/ProductOutput.php
```
