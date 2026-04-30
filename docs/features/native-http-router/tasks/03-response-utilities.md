# Response utilities

## 描述
实现响应辅助函数（`src/response.js`）：
- `sendJson(res, statusCode, data)` — 序列化 data 为 JSON，设置 Content-Type 为 `application/json`，设置 Content-Length，调用 `res.end()`
- `sendText(res, statusCode, text)` — 写入纯文本，设置 Content-Type 为 `text/plain; charset=utf-8`，设置 Content-Length，调用 `res.end()`

## 前置依赖
- 01-project-scaffold

## 验收标准
- [ ] `sendJson(res, 200, { key: 'val' })` 输出合法 JSON，Content-Type 为 `application/json`
- [ ] `sendText(res, 200, 'hello')` 输出纯文本，Content-Type 为 `text/plain; charset=utf-8`
- [ ] 两个函数均设置正确的 Content-Length 头
- [ ] 两个函数均调用 `res.end()` 结束响应

## 涉及文件
- `src/response.js`

## 验证方式
```bash
node -e "
const { sendJson, sendText } = require('./src/response');
const http = require('http');
// Mock response
const captured = {};
const mockRes = {
  headers: {},
  statusCode: 0,
  chunks: [],
  setHeader(name, val) { this.headers[name] = val; },
  writeHead(code) { this.statusCode = code; },
  write(chunk) { this.chunks.push(chunk); },
  end(chunk) {
    if (chunk) this.chunks.push(chunk);
    captured.body = Buffer.concat(this.chunks).toString();
    captured.headers = { ...this.headers };
    captured.statusCode = this.statusCode;
  }
};

sendJson(mockRes, 200, { message: 'hi' });
console.assert(captured.statusCode === 200, 'status code');
console.assert(captured.headers['Content-Type'] === 'application/json', 'json content-type');
console.assert(captured.body === JSON.stringify({ message: 'hi' }), 'json body');
console.log('sendJson OK');

const mockRes2 = { headers:{}, statusCode:0, chunks:[],
  setHeader(n,v){this.headers[n]=v}, writeHead(c){this.statusCode=c}, write(c){this.chunks.push(c)}, end(c){if(c)this.chunks.push(c); captured.body=Buffer.concat(this.chunks).toString(); captured.statusCode=this.statusCode}
};
sendText(mockRes2, 200, 'hello');
console.assert(captured.statusCode === 200, 'status code');
console.assert(captured.headers['Content-Type'] === 'text/plain; charset=utf-8', 'text content-type');
console.assert(captured.body === 'hello', 'text body');
console.log('sendText OK');
console.log('All Response tests passed');
"
```
