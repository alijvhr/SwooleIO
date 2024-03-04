FROM openswoole/swoole:latest

WORKDIR /var/www/swooleio

COPY composer.json .

COPY openswoole .

RUN composer update

COPY . .

CMD ["php", "server.php"]