# Create exception event listener

## 描述
创建 src/EventListener/ExceptionListener.php，监听 kernel.exception 事件，将领域异常和验证异常转换为统一的 JSON 错误响应。处理场景：CategoryNotFoundException→404, CategoryHasChildrenException→409, CircularReferenceException→422, \InvalidArgumentException→400 (VALIDATION_ERROR), \JsonException→400 (INVALID_JSON), 其他异常→500。响应格式遵循 {"error": {"code": "...", "message": "..."}}。

## 前置依赖
- 04-create-exceptions-dtos

## 验收标准
- [ ] src/EventListener/ExceptionListener.php 实现 EventSubscriberInterface，订阅 KernelEvents::EXCEPTION
- [ ] CategoryNotFoundException 返回 404 + {"error": {"code": "NOT_FOUND", "message": "..."}}
- [ ] CategoryHasChildrenException 返回 409 + {"error": {"code": "HAS_CHILDREN", "message": "..."}}
- [ ] CircularReferenceException 返回 422 + {"error": {"code": "CIRCULAR_REFERENCE", "message": "..."}}
- [ ] \InvalidArgumentException 返回 400 + {"error": {"code": "VALIDATION_ERROR", "message": "..."}}
- [ ] \JsonException (JSON 解析失败) 返回 400 + {"error": {"code": "INVALID_JSON", "message": "..."}}
- [ ] 异常监听器设置 JsonResponse 的 Content-Type 为 application/json

## 涉及文件
- app/demo-backend-api/src/EventListener/ExceptionListener.php

## 验证方式
```bash
cd app/demo-backend-api
vendor/bin/phpstan analyze src/EventListener/ExceptionListener.php
```
