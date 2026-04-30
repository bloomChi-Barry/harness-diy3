# Create domain exceptions and DTOs

## 描述
创建三个领域异常类：CategoryNotFoundException (404), CategoryHasChildrenException (409), CircularReferenceException (422)，均继承自 \RuntimeException 并包含 error code 常量。创建 CategoryInput DTO (readonly class) 用于接收创建/更新请求数据，包含所有可选字段和 name 必填校验；创建 CategoryOutput DTO 用于构建响应 JSON，支持 children 嵌套。

## 前置依赖
- 03-create-entity

## 验收标准
- [ ] src/Exception/CategoryNotFoundException.php 存在，携带 code: NOT_FOUND
- [ ] src/Exception/CategoryHasChildrenException.php 存在，携带 code: HAS_CHILDREN
- [ ] src/Exception/CircularReferenceException.php 存在，携带 code: CIRCULAR_REFERENCE
- [ ] src/Dto/CategoryInput.php 为 readonly class，包含 name(?string), parentId(?int), sortOrder(int), icon(?string), seoTitle(?string), seoDescription(?string), seoKeywords(?string), isEnabled(?bool)
- [ ] src/Dto/CategoryOutput.php 包含 fromEntity() 静态工厂方法和 toArray() 序列化方法，支持递归构建 children

## 涉及文件
- app/demo-backend-api/src/Exception/CategoryNotFoundException.php
- app/demo-backend-api/src/Exception/CategoryHasChildrenException.php
- app/demo-backend-api/src/Exception/CircularReferenceException.php
- app/demo-backend-api/src/Dto/CategoryInput.php
- app/demo-backend-api/src/Dto/CategoryOutput.php

## 验证方式
```bash
cd app/demo-backend-api
php -r "require 'vendor/autoload.php'; new App\Exception\CategoryNotFoundException(); new App\Exception\CategoryHasChildrenException(); new App\Exception\CircularReferenceException(); new App\Dto\CategoryInput(name: 'test'); echo 'OK';"
```
