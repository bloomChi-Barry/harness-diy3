# Knowledge Base Protocol

每个 skill 启动时（Step 1: 初始化上下文）必须遵循本协议，读取项目知识库以获取上下文。

## 步骤

检查 `.claude/knowledge/` 目录，读取以下文件（如存在）：

| 文件 | 内容 | 适用场景 |
|------|------|----------|
| `codebase-reality.md` | CLAUDE.md 未覆盖的实际约定 | 所有 skill |
| `verification-pitfalls.md` | 验证命令设计中的已知坑 | 所有 skill |
| `bug-patterns.md` | 已知 bug 模式及修复方法 | bug-fixer 必读；其他 skill 建议阅读 |

## 读取后的行为

- 设计验证命令时，对照 `verification-pitfalls.md` 的已知坑，避免重复
- 做架构决策时，对照 `codebase-reality.md` 的实际约定
- 分析 bug 时，对照 `bug-patterns.md` 的已知模式，判断是否匹配
