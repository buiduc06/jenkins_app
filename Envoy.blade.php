@servers(['main' => ['ec2-user@18.141.159.239']])

@setup
    $repository = 'https://github.com/buiduc06/jenkins_app.git';
    $base_dir = "/var/www/jenkins_app";
    $releases_dir = '/var/www/jenkins_app/releases';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir .'/'. $release;

function logMessage($message) {
return "echo '\033[32m" .$message. "\033[0m';\n";
}

@endsetup

@story('deploy')
    clone_repository
    update_symlinks
    run_composer
    run_install
    optimize_installation
    linking_current_release
    migrate_database
    bless_new_release
    clean_old_releases
    finish_deploy
@endstory

@task('clone_repository', ['on' => $server])
    {{ logMessage("🏃  Starting deployment...") }}
    {{ logMessage("🌀  Cloning repository...") }}
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }} -b {{ $branch }}
    cd {{ $new_release_dir }}
    git reset --hard {{ $commit }}

@endtask

@task('update_symlinks', ['on' => $server])
    {{ logMessage("🔗  Updating symlinks to persistent data...") }}
    # Remove the storage directory and replace with persistent data
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $base_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking .env file'
    ln -nfs {{ $base_dir }}/.env {{ $new_release_dir }}/.env

    echo 'symbolink storage to public'
    rm -rf {{ $new_release_dir }}/public/storage
    ln -nfs {{ $base_dir }}/storage/app/public {{ $new_release_dir }}/public/storage

@endtask

@task('run_composer', ['on' => $server])
    {{ logMessage("🚚  Running Composer...") }}
    cd {{ $new_release_dir }};
    composer install --no-interaction --prefer-dist --optimize-autoloader;
@endtask

@task('run_install', ['on' => $server])
    {{ logMessage("🔗 Starting deployment..." . $release) }}
    cd {{ $new_release_dir }}

    # Install node modules
    npm install

    # Build assets using Laravel Mix
    npm run production
@endtask


@task('optimize_installation', ['on' => $server])
    {{ logMessage("✨  Optimizing installation...") }}
    cd {{ $new_release_dir }};
    php artisan clear-compiled;
    # clear all cache
    php artisan optimize:clear
@endtask

@task('linking_current_release', ['on' => $server])
    {{ logMessage("🙏  Linking current release...") }}
    rm -rf {{ $base_dir }}/current
    ln -nfs {{ $new_release_dir }} {{ $base_dir }}/current
@endtask

@task('migrate_database', ['on' => $server])
    {{ logMessage("🙈  Migrating database...") }}
    cd {{ $new_release_dir }};
    php artisan migrate --force;
@endtask

@task('bless_new_release', ['on' => $server])
    {{ logMessage("🙏  Blessing new release...") }}
    cd {{ $new_release_dir }}

    # clear all cache
    php artisan optimize:clear

    # Clear caches
    php artisan cache:clear

    # Clear expired password reset tokens
    php artisan auth:clear-resets

    # Clear and cache routes
    php artisan route:clear
    # php artisan route:cache

    # Clear and cache config
    php artisan config:clear
    # php artisan config:cache

    # Clear and cache views
    php artisan view:clear
    php artisan view:cache

    # Restart queue
    php artisan queue:restart
    sudo systemctl restart supervisord

    # public access
    php artisan make:lang-js

    git config core.fileMode false

    # chmod
    sudo chmod -R 755 * .*
    sudo chown -R ec2-user:nginx * .*
    sudo chmod -R 775 storage bootstrap

@endtask

@task('clean_old_releases', ['on' => $server])
    {{ logMessage("🚾  Cleaning up old releases...") }}
    # Delete all but the 3 most recent.
    cd {{ $releases_dir }}
    ls -dt {{ $releases_dir }}/* | tail -n +3 | xargs -d "\n" sudo chown -R ec2-user:nginx .;
    ls -dt {{ $releases_dir }}/* | tail -n +3 | xargs -d "\n" rm -rf;
@endtask

@task('finish_deploy', ['on' => $server])
    {{ logMessage("🚀  Application deployed!") }}
@endtask
