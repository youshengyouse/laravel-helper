> 完善中
#  安装

```bash
composer require yousheng/laravel-helper
```



# 用法

 ### 路由部分

假设网站主机名为 http://www.abcd.com，那么 http://www.abcd.com/yousheng/list-routes 会查看当前所有解析后的路由

-  list-routes
- list-langs
- list-view-paths
- list-aliases
- list-publishes
- list-sessions

### 调试部分
http://nova2.demo.bendi/nova/dashboards/main?debug=xxx

如：http://backend.0he1.bendi/?debug=aliases             显示所有的别名

如：http://backend.0he1.bendi/?debug=middleware    显示所有中间件

-  routes
-  views
- aliases
- sessions
- publishes

##  生成迁移文件

```sh
php artisan convert:migrations --ignore="contents, contents_category"  # 排除这两个表
php artisan migrate:database yousheng_0he1_com --only="option,sells"   # 只有这两个表生成迁移文件
php artisan migrate:database yousheng_0he1_com
# 迁移整个yousheng_0he1_com数据库
# 生成文件放在 database/migrations/2020_12_25_034543_create_yousheng0he1com_database.php
```

不但可以生成表，同时也生成约束

## 生成Scope

```sh
php artisan make:scope PostNewest
```





## 下载B站视频

目标

- 获取指定url的链接和标题
- 将所有链接生成一txt文件，如abc.txt
- 使用you-get -I abc.txt 下载所有视频

