# Route definitions

## 描述
定义业务路由处理函数（`src/routes.js`）。导出 `configureRoutes(router)` 函数，注册三个路由：
1. `GET /` — 返回 HTML 欢迎页面
2. `GET /api/hello` — 返回 JSON `{ "message": "Hello, World!" }`
3. `GET /api/users` — 返回至少 3 个用户对象的 JSON 数组（含 id、name、email 字段）

## 前置依赖
- 02-router-core
- 03-response-utilities

## 验收标准
- [ ] `configureRoutes(router)` 精确注册 3 条 GET 路由：`/`、`/api/hello`、`/api/users`
- [ ] `/` handler 返回 HTML 欢迎消息，状态码 200
- [ ] `/api/hello` handler 返回 JSON `{ "message": "Hello, World!" }`，状态码 200
- [ ] `/api/users` handler 返回用户对象 JSON 数组（含 id、name、email），状态码 200
- [ ] 所有 handler 使用 `src/response.js` 的辅助函数（不直接操作 `res.write`）

## 涉及文件
- `src/routes.js`

## 验证方式
通过 T05 Server entry point 集成测试。
