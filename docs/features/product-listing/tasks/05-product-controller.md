# 创建 ProductController API 端点

## 描述
创建 `ProductController` 控制器，暴露 `GET /api/products` 端点。控制器薄层设计：解析查询参数、调用 ProductService、返回 JSON 响应。使用 OpenAPI 属性（NelmioApiDocBundle）自动生成 Swagger 文档。路由使用 `#[Route]` 属性定义。

## 前置依赖
- 04: 创建 ProductService 业务逻辑层

## 验收标准
- [ ] `src/Controller/ProductController.php` 文件存在且语法正确
- [ ] 使用 `#[AsController]` 和 `#[OA\Tag]` 属性
- [ ] `GET /api/products` 路由已注册，可通过 `debug:router` 查看
- [ ] 查询参数支持：category_id (integer, optional), keyword (string, optional), page (integer, default 1), limit (integer, default 20)
- [ ] 包含完整的 OpenAPI 文档属性（`#[OA\Get]`、参数、响应）
- [ ] 文件以 `declare(strict_types=1);` 开头
- [ ] 控制器方法不含业务逻辑，仅做参数解析和响应返回

## 涉及文件
- `/Users/barry/code/harness-diy3/app/demo-backend-api/src/Controller/ProductController.php`

## 验证方式

```bash
cd app/demo-backend-api && php -l src/Controller/ProductController.php && php bin/console debug:router | grep -qE "api/products"
```
