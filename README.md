# ZlsPHP

[使用文档](https://docs.73zls.com/zls-php/#/)

## 安装

### zls-cli

如果你已经安装过 [zls-cli](https://docs.73zls.com/zls-cli/) ，可以直接使用其命令直接下载到项目内。

```bash
zls make php zlsphp xxx #xxx表示项目名
```

### composer

如果你使用的是 composer ，使用下面的命令

```bash
composer create-project --prefer-dist zls/zls xxx #xxx表示项目名
```
***

## 启动

> 请自行安装好php并且设置好坏境变量

在根目录执行 

```bash
php zls artisan start
```

> 如果想非本机请访问请加上 `-host 0.0.0.0` ，默认端口是 `3780` 如要修改成8080 `-port 8080`。

看到输出 `HttpServe: http://127.0.0.1:3780` 表示启动成功，打开浏览器输入 http://127.0.0.1:3780

## 更多指令说明

```bash
php zls artisan -h
```

### 注意

请给 application/storage 目录可写入权限


2018-06-08 16:23:27