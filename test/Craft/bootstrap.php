<?php

declare(strict_types=1);

/*
 * Boots the sandbox's Craft site as a web application against the test
 * database, for the Craft integration suite. Project config is never written
 * back to the sandbox's YAML files.
 */

$project = getenv('CRAFT_PROJECT_PATH');
$project = is_string($project) && '' !== $project ? $project : getcwd();

if (false === $project || ! is_file("{$project}/bootstrap.php")) {
    fwrite(STDERR, data: "Run the Craft suite from the sandbox project (see phpunit.craft.xml).\n");
    exit(1);
}

$_SERVER += [
    'HTTP_HOST'       => 'localhost',
    'SERVER_NAME'     => 'localhost',
    'SERVER_PORT'     => '80',
    'REQUEST_METHOD'  => 'GET',
    'REQUEST_URI'     => '/',
    'SCRIPT_NAME'     => '/index.php',
    'SCRIPT_FILENAME' => "{$project}/web/index.php",
];

require "{$project}/bootstrap.php";

$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';

if (! $app->getIsInstalled()) {
    fwrite(
        STDERR,
        data: "The craft_images_test database isn't installed. Run test/Craft/install-test-db.sh from the sandbox.\n",
    );
    exit(1);
}

$app->getProjectConfig()->writeYamlAutomatically = false;

/*
 * PHPUnit runs from the command line, so Yii treats the request as a console
 * request and plugins pick their console controllers while Craft boots. Make
 * it a web request, and give the plugin the controller namespace Craft would
 * have chosen for one.
 */
$app->getRequest()->setIsConsoleRequest(false);
$app->getPlugins()->getPlugin('p-images')->controllerNamespace = 'parallelogram\\images\\controllers';
