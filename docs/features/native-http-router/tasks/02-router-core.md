# Router core

## 描述
实现 Router 类（`src/router.js`），内部维护路由表数组，提供 `register(method, path, handler)` 注册方法和 `resolve(method, path)` 匹配方法。匹配方式为精确匹配。

## 前置依赖
- 01-project-scaffold

## 验收标准
- [ ] `Router.register(method, path, handler)` 将路由元组 `{ method, path, handler }` 存入内部表
- [ ] `Router.resolve(method, path)` 在 method+path 完全匹配时返回对应 handler，否则返回 null
- [ ] Method 匹配大小写敏感，统一使用大写（GET、POST 等）
- [ ] Path 匹配为精确匹配

## 涉及文件
- `src/router.js`

## 验证方式
```bash
node -e "
const Router = require('./src/router');
const r = new Router();
const fn = (req, res) => {};
r.register('GET', '/test', fn);
console.assert(r.resolve('GET', '/test') === fn, 'match ok');
console.assert(r.resolve('GET', '/other') === null, 'no match ok');
console.assert(r.resolve('POST', '/test') === null, 'method mismatch ok');
console.log('All Router tests passed');
"
```
