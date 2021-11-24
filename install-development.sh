sudo chmod -R 755 * .*
sudo chown -R ec2-user:nginx * .*
sudo chmod -R 775 storage bootstrap

# Turn on maintenance mode
php artisan down

# Pull the latest changes from the git repository
git add .
git reset --hard
git clean -df
git pull origin develop

# Install/update composer dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader

# Run database migrations
php artisan migrate
#php artisan migrate:refresh --force
#php artisan db:seed --force

# clear all cache
php artisan optimize:clear

# Clear caches
php artisan cache:clear

# Clear expired password reset tokens
php artisan auth:clear-resets

# Clear and cache routes
php artisan route:clear
php artisan route:cache

# Clear and cache config
php artisan config:clear
#php artisan config:cache

# Clear and cache views
php artisan view:clear
php artisan view:cache

# Restart queue
php artisan queue:restart
sudo systemctl restart supervisord

# Install node modules
npm install

# Build assets using Laravel Mix
npm run dev

# php artisan passport:install

# Turn off maintenance mode
php artisan up

sudo chmod -R 755 * .*
sudo chown -R ec2-user:nginx * .*
sudo chmod -R 775 storage bootstrap

