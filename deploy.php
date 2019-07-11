<?php
namespace Deployer;

require 'recipe/symfony.php';

// Project name
set('allow_anonymous_stats', false);

// Project name
set('application', 'edicem');
set('ssh_type', 'native');
set('use_relative_symlinks', false);

set('ssh_multiplexing', false);
set('git_tty', false);

set('clear_use_sudo', true);
set('cleanup_use_sudo', true);
set('writable_use_sudo', true);

// Project repository
set('repository', 'git@github.com:boutin-killian/edycem.git');

// Shared files/dirs between deploys
add('shared_files', [
    '.env',
    'public/.htaccess',
    'public/robots.txt'
]);
add('shared_dirs', []);

// Writable dirs by web server
add('writable_dirs', []);

set('clear_paths', [
    './README.md',
    './.gitignore',
    './.git',
    './.php_cs',
    './.idea',
    './phpunit.xml.dist',
    './package.json',
    './package-lock.json',
    './symfony.lock',
    './web/webpack.config.js',
    './web/postcss.config.js',
    './node_modules',
    './deploy.php',
    './composer.phar',
    './composer.lock'
]);

// Hosts
host('production')
    ->hostname('35.205.78.82')
    ->user('root')
    ->forwardAgent()
    ->set('deploy_path', '/var/www/html');

// Tasks
set('default_stage', 'production');

// BUILD PRODUCTION ASSETS
desc('Build CSS/JS and deploy local built files');
task('deploy:build_local_assets', function () {
    runLocally('npm install');
    runLocally('npm run prod');
    upload('./public/build', '{{release_path}}/public/.');
});

after('deploy:failed', 'deploy:unlock');

// TASKS
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:build_local_assets',
    'deploy:cache:clear',
    'deploy:cache:warmup',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy');

// Display success message on completion
after('deploy', 'success');

// Migrate database before symlink new release.
before('deploy:symlink', 'database:migrate');