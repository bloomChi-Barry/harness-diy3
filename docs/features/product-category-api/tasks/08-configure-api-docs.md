# Configure API documentation (NelmioApiDoc + Swagger)

## 描述
配置 nelmio/api-doc-bundle 使其可用，在 config/bundles.php 中注册 NelmioApiDocBundle，创建 config/packages/nelmio_api_doc.yaml 和 config/routes/nelmio_api_doc.yaml。为 CategoryController 的 7 个端点添加 swagger-php OA\ 注解（#[OA\Get], #[OA\Post], #[OA\Put], #[OA\Delete], #[OA\Patch]），包括路径参数、请求体 schema、响应 schema 和错误响应描述。确保访问 /api/doc 可看到 Swagger UI。

## 前置依赖
- 07-create-controller

## 验收标准
- [ ] config/bundles.php 中已注册 Nelmio\ApiDocBundle\NelmioApiDocBundle
- [ ] config/packages/nelmio_api_doc.yaml 存在，配置 documentation.info 和 areas
- [ ] config/routes/nelmio_api_doc.yaml 存在，导出 /api/doc 路径
- [ ] CategoryController 中每个端点均有完整 OA\ 注解：summary, parameters, requestBody, responses
- [ ] 访问 /api/doc 可看到 Swagger UI 界面，展示全部 7 个端点
- [ ] Swagger UI 中可展开每个端点的请求/响应示例

## 涉及文件
- app/demo-backend-api/config/bundles.php
- app/demo-backend-api/config/packages/nelmio_api_doc.yaml
- app/demo-backend-api/config/routes/nelmio_api_doc.yaml
- app/demo-backend-api/src/Controller/CategoryController.php

## 验证方式
```bash
cd app/demo-backend-api
php -S localhost:8000 -t public/ &
curl -s http://localhost:8000/api/doc | head -20
```
