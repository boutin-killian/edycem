<?php
namespace Deployer;

require 'recipe/common.php';

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

set('shared_dirs', ['var/log', 'var/sessions']);
set('shared_files', ['.env']);
set('writable_dirs', ['var']);
set('migrations_config', '');

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
    ->hostname('34.76.155.185')
    ->user('root')
    ->forwardAgent()
    ->set('deploy_path', '/var/www/html');

// Tasks
set('default_stage', 'production');

set('bin/console', function () {
    return parse('{{bin/php}} {{release_path}}/bin/console --no-interaction');
});

desc('Migrate database');
task('database:migrate', function () {
    $options = '--allow-no-migration';
    if (get('migrations_config') !== '') {
        $options = sprintf('%s --configuration={{release_path}}/{{migrations_config}}', $options);
    }
    run(sprintf('{{bin/console}} doctrine:migrations:migrate %s', $options));
});

desc('Clear cache');
task('deploy:cache:clear', function () {
    run('{{bin/console}} cache:clear --no-warmup');
});
desc('Warm up cache');
task('deploy:cache:warmup', function () {
    run('{{bin/console}} cache:warmup');
});

// BUILD PRODUCTION ASSETS
desc('Build CSS/JS and deploy local built files');
task('deploy:build_local_assets', function () {
    runLocally('npm install');
    runLocally('npm run watch');
    upload('../../public/build', '{{release_path}}/.');
});

after('deploy:failed', 'deploy:unlock');

// TASKS
desc('Deploy project');
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