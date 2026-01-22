<?php

$site_schema ??= true;
$page_schema ??= true;

foreach (array_merge($site_schema ? $site->schemas() : [], $page_schema ? $page->schemas() : []) as $schema) {
	echo $schema;
}
