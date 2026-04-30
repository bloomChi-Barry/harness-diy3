# Native HTTP Router - 需求说明书

## 1. 概述

- **业务目标**: 学习/测试用途，使用 Node.js 原生 `http` 模块搭建一个带基础路由功能的 HTTP Server
- **功能背景**: 不依赖任何第三方框架（如 Express），手写路由分发逻辑，深入理解 Node.js HTTP 模块的工作原理

## 2. 目标用户

- **用户角色**: 开发者（自用学习/测试）
- **使用场景**: 本地启动服务器，通过浏览器或 curl 访问不同路径验证路由功能

## 3. 功能需求

### P0 - 核心功能

| # | 功能 | 描述 |
|---|------|------|
| F1 | 服务器启动 | 使用 `http.createServer` 创建服务，监听指定端口（默认 3000） |
| F2 | 路由注册 | 支持注册不同路径（GET /、GET /api/hello、GET /api/users 等）和处理函数 |
| F3 | 路由匹配 | 根据请求的 method + path 匹配对应 handler，返回响应 |
| F4 | JSON 响应 | 支持返回 JSON 格式数据，自动设置 `Content-Type: application/json` |
| F5 | 静态文本响应 | 支持返回纯文本/HTML 格式响应 |

### P1 - 补充功能

| # | 功能 | 描述 |
|---|------|------|
| F6 | 404 处理 | 未匹配到路由时返回 404 状态码和提示信息 |
| F7 | 启动日志 | 服务器启动后在控制台输出监听地址 |

## 4. 非功能需求

- **性能要求**: 无特殊要求，学习测试用途
- **安全要求**: 无特殊要求
- **兼容性要求**: Node.js v18+

## 5. 技术约束

- **技术栈**: Node.js 原生 `http` 模块，不使用 Express/Koa 等框架
- **部署方式**: 本地直接运行 `node server.js`
- **外部依赖**: 无

## 6. 验收标准

- [ ] AC1: 执行 `node server.js` 后服务器成功启动，控制台输出监听地址
- [ ] AC2: `curl http://localhost:3000/` 返回 200 状态码及预期响应内容
- [ ] AC3: `curl http://localhost:3000/api/hello` 返回 JSON 格式 `{"message": "Hello, World!"}`
- [ ] AC4: `curl http://localhost:3000/api/users` 返回 JSON 格式用户列表
- [ ] AC5: `curl http://localhost:3000/nonexistent` 返回 404 状态码
- [ ] AC6: 所有验证项可通过 `bash docs/features/native-http-router/verify.sh` 一键测试

## 7. 边界与异常

- 未匹配路由返回 `404 Not Found` + JSON `{"error": "Not Found"}`
- 服务器端口被占用时给出明确错误提示
- 请求方法不匹配（如 POST 访问 GET 路由）返回 404
