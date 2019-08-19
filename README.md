# ZlsPHP

[使用文档](https://docs.73zls.com/zls-php/#/)

## 安装依赖包

```bash
# 开发环境
composer install

# 生产环境
composer install --no-dev
```
***

## 启动

> 请自行安装好php并且设置好坏境变量

在根目录执行 

```bash
php zls start
```

> 如需外网访问请加上 `-C`，默认端口是 `3780` 如要修改成8080 `--port 8080`，更多选项请参考 `-H`。

输出如 `HttpServe: http://127.0.0.1:3780` 表示启动成功，打开浏览器输入 http://127.0.0.1:3780

## 更多指令说明

```bash
php zls -h
```

## Git 规范

```bash
git config core.hooksPath .githook
git config core.autocrlf input
git config core.safecrlf warn
```

## 注意

请给 app/storage 目录可写入权限


2019-08-19 16:25:03