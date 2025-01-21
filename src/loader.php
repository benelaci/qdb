<?php

/*
	The classes of qdb should not be loaded automatically, as one point of it
	is that you only load the variant you need.

	This file is the one to be autoloaded. It serves to minimize the hassle of
	manually loading the variant, given that Composer's autoload feature is
	not being utilized properly.
*/

function qdb_load($variant) {
	require_once 'vendor/benelaci/qdb/src/'.ucfirst($variant).'.php';
}