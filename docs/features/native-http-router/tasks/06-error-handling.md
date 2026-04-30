# Error handling

## 描述
增强 `server.js` 的错误处理：
1. `server.listen()` 捕获 `EADDRINUSE` 错误，输出 `Error: Port <port> is already in use.`
2. 其他 listen 错误输出包含错误描述的消息
3. 注册 `SIGTERM` / `SIGINT` 处理器，优雅关闭服务器并退出（code 0）

## 前置依赖
- 05-server-entry-point

## 验收标准
- [ ] `EADDRINUSE` 输出包含端口号的可读错误信息
- [ ] 其他 listen 错误输出包含错误描述
- [ ] `SIGTERM`（`kill <pid>`）触发 `server.close()` 并正常退出
- [ ] `SIGINT`（`Ctrl+C`）触发 `server.close()` 并正常退出

## 涉及文件
- `server.js`

## 验证方式
```bash
# 测试端口占用
node server.js &
PID=$!
sleep 1
node server.js 2>&1 || true
kill $PID 2>/dev/null

# 测试优雅关闭
node server.js &
PID=$!
sleep 1
kill $PID
wait $PID && echo "Graceful shutdown OK"
```
