<?php

declare(strict_types=1);

namespace OCA\ObsidianViewer\Controller;

use OCA\ObsidianViewer\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	public function __construct(IRequest $request) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'obsidian_viewer-main');
		Util::addStyle(Application::APP_ID, 'obsidian_viewer-main');
		return new TemplateResponse(Application::APP_ID, 'main');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function catchAll(string $path): TemplateResponse {
		return $this->index();
	}
}
