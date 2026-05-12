FROM php:8.2-cli

WORKDIR /app

COPY . /app

# نزيد mysqli و pdo_mysql باش يتصل بـ MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نزيد Composer
RUN apt-get update && apt-get install -y unzip git \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# نثبت المكتبات اللي تحتاجها (Twilio, FPDF)
RUN composer require twilio/sdk setasign/fpdf

CMD ["php", "-S", "0.0.0.0:10000", "server.php"]
