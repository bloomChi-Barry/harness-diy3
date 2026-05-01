# api-doc-404 - BUG 分析

## 1. 现象

- 访问 `http://127.0.0.1:8099/api/doc` 时，Swagger UI 页面加载成功，但显示 **"Failed to load API definition"**
- 浏览器中 Swagger UI JS 请求 `/api/doc.json` 获取 OpenAPI 规范失败
- 服务端日志：`GET /api/doc.json → [404] - No such file or directory`
- 日志中无任何 Symfony routing 痕迹，说明请求未到达 Symfony Kernel

## 2. 复现步骤

1. 使用 PHP 内置服务器启动项目：`php -S 127.0.0.1:8099 -t ./public`
2. 浏览器访问 `http://127.0.0.1:8099/api/doc`
3. 见 Swagger UI 界面加载但显示 "Failed to load API definition"
4. 直接访问 `http://127.0.0.1:8099/api/doc.json` → HTTP 404

## 3. 期望行为

- 访问 `/api/doc` 时 Swagger UI 正确加载并展示 API 文档
- `/api/doc.json` 返回 OpenAPI JSON 规范（HTTP 200）

## 4. 影响范围

- **严重程度**：P1 — 开发者无法查看 API 文档，影响开发体验
- **影响用户**：所有使用 PHP 内置服务器运行项目的开发者
- **影响环境**：仅 PHP 内置服务器（`php -S`），不影响生产环境（Nginx/Apache）

## 5. 根因确认

PHP 内置服务器对 URI 的默认路由策略：

1. 如果 URI 映射到文档根目录下的真实文件 → 直接返回文件
2. 如果 URI 映射到真实目录 → 查找目录中 index.php/index.html
3. 如果 **URI 不含文件扩展名** → 向上遍历目录查找 index.php，最终找到 `public/index.php`（Symfony 前端控制器）
4. 如果 **URI 包含文件扩展名**（如 `.json`）→ 视为静态文件请求，文件不存在则直接 404，**不执行 index.php 回退**

`/api/doc` 不含扩展名 → 回退到 `public/index.php` → Symfony 路由匹配 → 200 ✅
`/api/doc.json` 含 `.json` 扩展名 → 视为静态文件 → `public/api/doc.json` 不存在 → 404 ❌

**验证命令**（复现）：
```bash
# 启动服务器
php -S 127.0.0.1:8099 -t ./public > /dev/null 2>&1 &
sleep 1
# 不带扩展名的路径正常工作
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8099/api/doc
# 期望 200
# 带 .json 的路径失败
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8099/api/doc.json
# 期望 404（BUG 表现）
```

## 6. 修复方向

为 PHP 内置服务器添加路由脚本，使所有请求都经过 `public/index.php`（Symfony 前端控制器），确保 Symfony Router 匹配所有路由。

CLAUDE.md 中的开发命令也需要相应更新。
