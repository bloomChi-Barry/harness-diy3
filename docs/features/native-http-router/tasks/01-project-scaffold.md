# Project scaffold

## 描述
创建最小化的项目骨架：初始化 package.json（含 name、version、main 入口和 start 脚本），创建 .gitignore 文件和 src/ 目录。不依赖任何第三方包。

## 前置依赖
- 无

## 验收标准
- [ ] package.json 存在，`main` 设置为 `server.js`，`start` 脚本指向 `node server.js`
- [ ] .gitignore 存在，覆盖 `node_modules/`、`*.log`、`.DS_Store`
- [ ] src/ 目录已创建

## 涉及文件
- `package.json`
- `.gitignore`

## 验证方式
```bash
node -e "const p = require('./package.json'); console.assert(p.main === 'server.js', 'main ok'); console.assert(p.scripts.start === 'node server.js', 'start ok')"
test -f .gitignore && echo ".gitignore exists"
test -d src && echo "src/ exists"
```
