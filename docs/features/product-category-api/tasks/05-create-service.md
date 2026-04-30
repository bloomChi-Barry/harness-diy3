# Create CategoryService

## 描述
实现 CategoryService，包含全部 7 种业务操作的核心逻辑：getTree (构建嵌套分类树，按 sort_order ASC / id ASC 排序，支持 enabled_only 过滤), getById (获取单个分类及其子级), create (创建分类，校验 parent_id 存在性), update (部分更新字段，校验 parent_id 循环引用), delete (检查子级存在性，有子级时抛异常), toggle (切换 is_enabled), move (变更父级和排序，校验循环引用)。使用 Doctrine EntityManagerInterface 进行持久化。循环引用检测通过检查目标 parent_id 是否为当前节点或其子孙节点实现。

## 前置依赖
- 04-create-exceptions-dtos

## 验收标准
- [ ] getTree(bool $enabledOnly = false) 返回嵌套树形结构数组，排序正确
- [ ] getById(int $id) 返回单个分类及其直接子级，不存在时抛出 CategoryNotFoundException
- [ ] create(CategoryInput $input) 创建分类并返回 Category 实体，name 为空时抛出 \InvalidArgumentException
- [ ] update(int $id, CategoryInput $input) 部分更新字段，parentId 设为自身或其子孙时抛出 CircularReferenceException
- [ ] delete(int $id) 在分类有子级时抛出 CategoryHasChildrenException，无子级时删除并返回 void
- [ ] toggle(int $id, bool $isEnabled) 切换启用状态
- [ ] move(int $id, ?int $newParentId, int $sortOrder) 移动分类，循环引用检测生效
- [ ] PHPStan level 6 分析通过（无类型错误）

## 涉及文件
- app/demo-backend-api/src/Service/CategoryService.php

## 验证方式
```bash
cd app/demo-backend-api
vendor/bin/phpstan analyze src/Service/CategoryService.php
```
