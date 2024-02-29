<?php

/**
 * Delivery script for assets
 * Adapted from Web Access Checker
 * @see wac.php
 */

chdir('../../../../../../../');
require_once('./libs/composer/vendor/autoload.php');


$container = new \ILIAS\DI\Container();
$GLOBALS["DIC"] = $container;

$container['http.request_factory'] = static fn ($c) => new \ILIAS\HTTP\Request\RequestFactoryImpl();
$container['http.response_factory'] = static fn ($c) => new \ILIAS\HTTP\Response\ResponseFactoryImpl();
$container['http.cookie_jar_factory'] = static fn ($c) => new \ILIAS\HTTP\Cookies\CookieJarFactoryImpl();
$container['http.response_sender_strategy'] = static fn ($c) => new \ILIAS\HTTP\Response\Sender\DefaultResponseSenderStrategy();
$container['http.duration_factory'] = static fn ($c) => new \ILIAS\HTTP\Duration\DurationFactory(
    new \ILIAS\HTTP\Duration\Increment\IncrementFactory()
);
$container['http'] = static fn ($c) => new \ILIAS\HTTP\Services($c);


/** @var \ILIAS\HTTP\Services $services */
$services = $container['http'];
$delivery = new ilTestArchiveCreatorAssetsDelivery($services);
$delivery->handleRequest();

