<?php

declare(strict_types=1);

namespace OCA\ObsidianViewer\Controller;

use OCA\ObsidianViewer\AppInfo\Application;
use OCA\ObsidianViewer\Service\VaultService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class VaultController extends Controller {
	private VaultService $vaultService;

	public function __construct(IRequest $request, VaultService $vaultService) {
		parent::__construct(Application::APP_ID, $request);
		$this->vaultService = $vaultService;
	}

	private function resolveVaultPath(string $vault): ?string {
		$vaults = $this->vaultService->findVaults();
		foreach ($vaults as $v) {
			if ($v['name'] === $vault) {
				return $v['path'];
			}
		}
		return null;
	}

	#[NoAdminRequired]
	public function list(): JSONResponse {
		$vaults = $this->vaultService->findVaults();
		return new JSONResponse(['vaults' => $vaults]);
	}

	#[NoAdminRequired]
	public function files(string $vault): JSONResponse {
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$tree = $this->vaultService->getFileTree($vaultPath);
		return new JSONResponse(['tree' => $tree]);
	}

	#[NoAdminRequired]
	public function graph(string $vault): JSONResponse {
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$mdLinks = $this->request->getParam('mdLinks', '0') === '1';
		return new JSONResponse($this->vaultService->getLinkGraph($vaultPath, $mdLinks));
	}

	#[NoAdminRequired]
	public function search(string $vault): JSONResponse {
		$query = trim($this->request->getParam('q', ''));
		if ($query === '') {
			return new JSONResponse(['results' => []]);
		}
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$results = $this->vaultService->searchVault($vaultPath, $query);
		return new JSONResponse(['results' => $results]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function query(string $vault): JSONResponse {
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$body = json_decode(file_get_contents('php://input'), true);
		if (!is_array($body)) {
			return new JSONResponse(['error' => 'Invalid JSON body'], 400);
		}
		$fields = $body['fields'] ?? [];
		$filter = $body['filter'] ?? '';
		$sort = $body['sort'] ?? null;
		$limit = min((int)($body['limit'] ?? 100), 500);

		if (!is_array($fields) || empty($fields)) {
			return new JSONResponse(['error' => 'fields must be a non-empty array'], 400);
		}

		$results = $this->vaultService->queryVault($vaultPath, $fields, $filter, $sort, $limit);
		return new JSONResponse($results);
	}

	#[NoAdminRequired]
	public function properties(string $vault): JSONResponse {
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$properties = $this->vaultService->getVaultProperties($vaultPath);
		return new JSONResponse(['properties' => $properties]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function rawFile(string $vault) {
		$name = $this->request->getParam('name', '');
		if ($name === '' || str_contains($name, '..') || str_contains($name, '/')) {
			return new JSONResponse(['error' => 'Invalid name'], 400);
		}
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$file = $this->vaultService->findFileByName($vaultPath, $name);
		if ($file === null) {
			return new JSONResponse(['error' => 'File not found'], 404);
		}
		$response = new DataDisplayResponse($file->getContent());
		$response->addHeader('Content-Type', $file->getMimeType());
		$response->cacheFor(3600);
		return $response;
	}

	#[NoAdminRequired]
	public function fileInfo(string $vault): JSONResponse {
		$name = $this->request->getParam('name', '');
		if ($name === '' || str_contains($name, '..')) {
			return new JSONResponse(['error' => 'Invalid name'], 400);
		}
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$result = $this->vaultService->getFileInfo($vaultPath, $name);
		if ($result === null) {
			return new JSONResponse(['error' => 'File not found'], 404);
		}
		return new JSONResponse($result);
	}

	#[NoAdminRequired]
	public function fileContent(string $vault): JSONResponse {
		$path = $this->request->getParam('path', '');
		if (str_contains($path, '..')) {
			return new JSONResponse(['error' => 'Invalid path'], 400);
		}
		$vaultPath = $this->resolveVaultPath($vault);
		if ($vaultPath === null) {
			return new JSONResponse(['error' => 'Vault not found'], 404);
		}
		$content = $this->vaultService->getFileContent($vaultPath, $path);
		if ($content === null) {
			return new JSONResponse(['error' => 'File not found'], 404);
		}
		return new JSONResponse(['content' => $content]);
	}
}
