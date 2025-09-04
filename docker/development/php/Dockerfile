FROM php:8.2-apache

# Definindo argumentos
ARG uid
ARG user

# Instalando dependências necessárias
RUN apt-get update && apt-get install -y --no-install-recommends curl libpng-dev libonig-dev libxml2-dev libpq-dev zip unzip zlib1g-dev libmcrypt-dev libzip-dev libsodium-dev && pecl install xdebug && docker-php-ext-enable xdebug && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml exif pcntl bcmath gd zip sodium && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


# Copiando o Composer
COPY --from=composer:2.5.8 /usr/bin/composer /usr/local/bin/composer

# Configurando usuário
RUN useradd -G www-data,root -u $uid -d /home/$user $user && \
    mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Configurando PATH
ENV PATH="$PATH:/home/$user/.composer/vendor/bin"

# Definindo o usuário padrão
USER $user
