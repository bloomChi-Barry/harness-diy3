# Create CategoryController (all 7 endpoints)

## 描述
创建 src/Controller/CategoryController.php，使用 PHP Attribute 路由，遵循 thin-controller 模式。实现 7 个端点：GET /api/categories (获取树，支持 ?enabled_only=true 查询参数), GET /api/categories/{id} (获取详情), POST /api/categories (创建，返回 201 + Location 头), PUT /api/categories/{id} (更新), DELETE /api/categories/{id} (删除，成功返回 204), PATCH /api/categories/{id}/toggle (切换启用状态), PATCH /api/categories/{id}/move (移动分类)。每个方法：解析请求 JSON → 委托 CategoryService → 返回 JsonResponse。使用构造函数注入 CategoryService。对于 POST/PUT，手动 json_decode 请求体并构造 CategoryInput DTO；对于 PATCH toggle/move，解析请求体中的特定字段。

## 前置依赖
- 05-create-service
- 06-create-exception-listener

## 验收标准
- [ ] GET /api/categories?enabled_only=true 返回过滤后的树形 JSON
- [ ] GET /api/categories/{id} 存在时返回 200 + JSON，不存在时返回 404
- [ ] POST /api/categories 成功时返回 201 + Location 头，缺少 name 时返回 400
- [ ] PUT /api/categories/{id} 成功返回 200 + 更新后的 JSON，循环引用时返回 422
- [ ] DELETE /api/categories/{id} 有子级时返回 409，不存在时返回 404，成功时返回 204
- [ ] PATCH /api/categories/{id}/toggle 成功返回 200 + 更新后的 JSON
- [ ] PATCH /api/categories/{id}/move 成功返回 200，循环引用时返回 422
- [ ] 所有端点可通过 bin/console debug:router 查看
- [ ] Controller 方法不超过 15 行业务逻辑（仅解析请求 + 调用服务 + 返回响应）

## 涉及文件
- app/demo-backend-api/src/Controller/CategoryController.php

## 验证方式
```bash
cd app/demo-backend-api
php bin/console debug:router
php -S localhost:8000 -t public/ &
# 手动 curl 测试各端点
```
