# Verification script

## 描述
创建自动化验证脚本 `docs/features/native-http-router/verify.sh`：
1. 以后台进程启动服务器，记录 PID
2. 轮询等待服务器在端口 3000 就绪
3. 用 curl 逐项测试所有验收标准（4 个路由 + 404）
4. 对比响应内容与预期（状态码 + body）
5. 每个测试输出 PASS/FAIL
6. 测试结束后 kill 服务器，整体退出码（0 = 全部通过，1 = 有失败）

## 前置依赖
- 06-error-handling

## 验收标准
- [ ] `bash verify.sh` 从项目根目录执行，启动服务器 - 运行全部测试 - 停止服务器
- [ ] 测试项覆盖：GET /（200）、GET /api/hello（200 + JSON）、GET /api/users（200 + JSON array）、GET /nonexistent（404）
- [ ] 每项测试输出 PASS 或 FAIL 及描述标签
- [ ] 仅当全部测试通过时退出码为 0
- [ ] 使用 `trap` 确保脚本退出时一定 kill 服务器

## 涉及文件
- `docs/features/native-http-router/verify.sh`

## 验证方式
```bash
bash docs/features/native-http-router/verify.sh
echo "Exit code: $?"
```
