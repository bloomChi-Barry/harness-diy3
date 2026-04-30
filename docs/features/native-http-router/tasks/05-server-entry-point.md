# Server entry point

## 描述
创建 `server.js` 作为应用入口。使用 `http.createServer` 创建服务，集成 Router、response 辅助函数和路由定义。请求处理逻辑：
1. 用 Router.resolve 匹配 handler
2. 匹配成功则调用 handler
3. 匹配失败返回 404 JSON 响应 `{ "error": "Not Found" }`
监听端口优先使用 `process.env.PORT`，默认 3000。启动后在控制台输出 `Server listening on http://localhost:<port>`。

## 前置依赖
- 04-route-definitions

## 验收标准
- [ ] `node server.js` 成功启动 HTTP 服务器
- [ ] 控制台输出 `Server listening on http://localhost:3000`（或自定义 PORT）
- [ ] 未匹配的 GET 请求返回 404 和 JSON `{ "error": "Not Found" }`
- [ ] POST `/api/hello`（method 不匹配）返回 404
- [ ] 三条注册路由返回正确响应（与 T04 一致）

## 涉及文件
- `server.js`

## 验证方式
```bash
node server.js &
SERVER_PID=$!
sleep 1
curl -s http://localhost:3000/ && echo " - GET / OK"
curl -s http://localhost:3000/api/hello && echo " - GET /api/hello OK"
curl -s http://localhost:3000/api/users && echo " - GET /api/users OK"
curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/nonexistent && echo " - GET /nonexistent (404)"
kill $SERVER_PID
```
