#! /usr/bin/env php
<?php
	
	use Symfony\Component\Console\Application;
 
	
	require 'vendor/autoload.php';
	
	$app = new Application("Laracast Demo version 1.0");
	
	$app->add(new \Commands\SayHello());
	$app->add(new \Commands\SetupWP(new \GuzzleHttp\Client()));
	
	$app->run();