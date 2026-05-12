FROM php:8.2-cli

WORKDIR /app

COPY . /app

RUN apt-get update && apt-get install -y unzip git \
    && docker-php-ext-install mysqli pdo pdo_mysql

CMD ["php", "-S", "0.0.0.0:10000", "server.php"]

