<?php

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'vault#list', 'url' => '/api/vaults', 'verb' => 'GET'],
		['name' => 'vault#files', 'url' => '/api/vaults/{vault}/files', 'verb' => 'GET'],
		['name' => 'vault#graph', 'url' => '/api/vaults/{vault}/graph', 'verb' => 'GET'],
		['name' => 'vault#search', 'url' => '/api/vaults/{vault}/search', 'verb' => 'GET'],
		['name' => 'vault#properties', 'url' => '/api/vaults/{vault}/properties', 'verb' => 'GET'],
		['name' => 'vault#query', 'url' => '/api/vaults/{vault}/query', 'verb' => 'POST'],
		['name' => 'vault#fileInfo', 'url' => '/api/vaults/{vault}/fileinfo', 'verb' => 'GET'],
		['name' => 'vault#fileContent', 'url' => '/api/vaults/{vault}/file', 'verb' => 'GET'],
		['name' => 'vault#rawFile', 'url' => '/api/vaults/{vault}/raw', 'verb' => 'GET'],
		['name' => 'page#catchAll', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+']],
	],
];
