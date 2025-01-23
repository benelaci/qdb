<?php
/*    qdb - QueryDisburdener
          version 1.0.2

      User-side loader file


The classes should not be auto-loaded by Composer, as one point of it is
that you only load the variant you need.

This file is the one to be autoloaded. It serves to minimize the hassle of
manually loading the variant, given that Composer's autoload feature is
not being utilized properly.

*/


namespace qdb;

$file__constants = __DIR__.'/_constants.php';
if (file_exists($file__constants))
	include $file__constants;

function load($variant) {
	require_once __DIR__.'/'.ucfirst($variant).'.php';
}
