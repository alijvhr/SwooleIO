FROM openswoole/swoole:latest

WORKDIR /var/www/swooleio

COPY composer.json .

RUN composer update

COPY . .

COPY ./openswoolecore/core ./vendor/openswoole/core

CMD ["php", "server.php"]