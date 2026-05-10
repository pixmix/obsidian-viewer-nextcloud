<?php

declare(strict_types=1);

namespace OCA\ObsidianViewer\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

class VaultService {
	private IRootFolder $rootFolder;
	private IUserSession $userSession;

	public function __construct(IRootFolder $rootFolder, IUserSession $userSession) {
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
	}

	public function findVaults(): array {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return [];
		}
		$vaults = [];
		$this->scanForVaults($userFolder, $vaults, 0, 5);
		return $vaults;
	}

	private function scanForVaults(Folder $folder, array &$vaults, int $depth, int $maxDepth): void {
		if ($depth > $maxDepth) {
			return;
		}
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof Folder) {
				if ($node->getName() === '.obsidian') {
					$vaults[] = [
						'name' => $folder->getName(),
						'path' => $folder->getPath(),
					];
					return;
				}
				if (!str_starts_with($node->getName(), '.')) {
					$this->scanForVaults($node, $vaults, $depth + 1, $maxDepth);
				}
			}
		}
	}

	public function getFileTree(string $vaultPath): array {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return [];
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$folder = $userFolder->get($relativePath);
			if (!($folder instanceof Folder)) {
				return [];
			}
			return $this->buildTree($folder, '');
		} catch (\Exception $e) {
			return [];
		}
	}

	private function buildTree(Folder $folder, string $prefix): array {
		$tree = [];
		$folders = [];
		$files = [];

		foreach ($folder->getDirectoryListing() as $node) {
			if (str_starts_with($node->getName(), '.')) {
				continue;
			}
			if ($node instanceof Folder) {
				$path = $prefix . '/' . $node->getName();
				$folders[] = [
					'name' => $node->getName(),
					'path' => $path,
					'type' => 'folder',
					'children' => $this->buildTree($node, $path),
				];
			} else {
				$name = $node->getName();
				$files[] = [
					'name' => $name,
					'path' => $prefix . '/' . $name,
					'type' => 'file',
					'mime' => $node->getMimeType(),
				];
			}
		}

		usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
		usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

		return array_merge($folders, $files);
	}

	public function getLinkGraph(string $vaultPath, bool $mdLinks = false): array {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return ['nodes' => [], 'edges' => []];
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$folder = $userFolder->get($relativePath);
			if (!($folder instanceof Folder)) {
				return ['nodes' => [], 'edges' => []];
			}

			$mdFiles = [];
			$canvasFiles = [];
			$this->collectVaultFiles($folder, '', $mdFiles, $canvasFiles);

			$byBasename = [];
			$byPath = [];
			$byPathLower = [];
			$byBasenameLower = [];
			foreach ($mdFiles as $f) {
				$byPath[$f['path']] = true;
				$byPathLower[strtolower($f['path'])] = $f['path'];
				$basename = preg_replace('/\.md$/', '', basename($f['path']));
				$byBasename[$basename][] = $f['path'];
				$basenameLower = strtolower($basename);
				$byBasenameLower[$basenameLower][] = $f['path'];
			}
			foreach ($canvasFiles as $f) {
				$byPath[$f['path']] = true;
				$byPathLower[strtolower($f['path'])] = $f['path'];
			}

			$nodes = [];
			foreach ($mdFiles as $f) {
				$nodes[] = [
					'id' => $f['path'],
					'name' => preg_replace('/\.md$/', '', basename($f['path'])),
					'type' => 'md',
					'mtime' => $f['mtime'],
				];
			}
			foreach ($canvasFiles as $f) {
				$nodes[] = [
					'id' => $f['path'],
					'name' => basename($f['path']),
					'type' => 'canvas',
					'mtime' => $f['mtime'],
				];
			}

			$noteExts = ['', 'md', 'canvas'];
			$edgeSet = [];
			$unresolvedNodes = [];
			foreach ($mdFiles as $f) {
				$linkContent = preg_replace('/```[\s\S]*?```/m', '', $f['content']);
				$linkContent = preg_replace('/`[^`]+`/', '', $linkContent);
				preg_match_all('/!?\[\[([^\]|#\\\\]+)/', $linkContent, $matches);
				$sourceDir = dirname($f['path']);
				foreach ($matches[1] as $target) {
					$target = trim($target, " \t\n\r\0\x0B\\");
					$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
					if (!in_array($ext, $noteExts)) {
						continue;
					}
					$result = $this->resolveTarget($target, $sourceDir, $byPath, $byBasename, $byPathLower, $byBasenameLower);
					if ($result !== null && $result['path'] !== $f['path']) {
						$key = $f['path'] . '>' . $result['path'];
						if (isset($edgeSet[$key]) && empty($edgeSet[$key]['caseMismatch'])) {
							continue;
						}
						$edge = ['source' => $f['path'], 'target' => $result['path']];
						if ($result['caseMismatch']) $edge['caseMismatch'] = true;
						$edgeSet[$key] = $edge;
					} elseif ($result === null) {
						$unresolvedId = 'unresolved:' . $target;
						if (!isset($unresolvedNodes[$unresolvedId])) {
							$unresolvedNodes[$unresolvedId] = [
								'id' => $unresolvedId,
								'name' => $target,
								'type' => 'unresolved',
								'mtime' => 0,
							];
						}
						$key = $f['path'] . '>' . $unresolvedId;
						$edgeSet[$key] = ['source' => $f['path'], 'target' => $unresolvedId];
					}
				}
			}

			if ($mdLinks) {
				foreach ($mdFiles as $f) {
					$linkContent = preg_replace('/```[\s\S]*?```/m', '', $f['content']);
					$linkContent = preg_replace('/`[^`]+`/', '', $linkContent);
					preg_match_all('/(?<!!)\[(?:[^\[\]]|\[[^\]]*\])*\]\(([^)]+)\)/', $linkContent, $mdMatches);
					$sourceDir = dirname($f['path']);
					foreach ($mdMatches[1] as $href) {
						$href = trim($href);
						if (preg_match('#^https?://#i', $href) || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
							continue;
						}
						$href = strtok($href, '#');
						$href = rawurldecode($href);
						if (str_contains($href, '../') || str_starts_with($href, './')) {
							$combined = $sourceDir . '/' . $href;
							$parts = [];
							foreach (explode('/', $combined) as $seg) {
								if ($seg === '..') { array_pop($parts); }
								elseif ($seg !== '' && $seg !== '.') { $parts[] = $seg; }
							}
							$href = implode('/', $parts);
						}
						$ext = strtolower(pathinfo($href, PATHINFO_EXTENSION));
						if (!in_array($ext, $noteExts)) {
							continue;
						}
						$result = $this->resolveTarget($href, $sourceDir, $byPath, $byBasename, $byPathLower, $byBasenameLower);
						if ($result !== null && $result['path'] !== $f['path']) {
							$key = $f['path'] . '>' . $result['path'];
							if (isset($edgeSet[$key]) && empty($edgeSet[$key]['caseMismatch'])) {
								continue;
							}
							$edge = ['source' => $f['path'], 'target' => $result['path']];
							if ($result['caseMismatch']) $edge['caseMismatch'] = true;
							$edgeSet[$key] = $edge;
						} elseif ($result === null) {
							$unresolvedId = 'unresolved:' . $href;
							if (!isset($unresolvedNodes[$unresolvedId])) {
								$unresolvedNodes[$unresolvedId] = [
									'id' => $unresolvedId,
									'name' => $href,
									'type' => 'unresolved',
									'mtime' => 0,
								];
							}
							$key = $f['path'] . '>' . $unresolvedId;
							$edgeSet[$key] = ['source' => $f['path'], 'target' => $unresolvedId];
						}
					}
				}
			}

			foreach ($canvasFiles as $cf) {
				$data = json_decode($cf['content'], true);
				if (!is_array($data) || !isset($data['nodes'])) {
					continue;
				}
				foreach ($data['nodes'] as $canvasNode) {
					if (($canvasNode['type'] ?? '') === 'file' && isset($canvasNode['file'])) {
						$target = '/' . $canvasNode['file'];
						if (isset($byPath[$target])) {
							$key = $cf['path'] . '>' . $target;
							$edgeSet[$key] = ['source' => $cf['path'], 'target' => $target];
						}
					}
					if (($canvasNode['type'] ?? '') === 'text' && isset($canvasNode['text'])) {
						preg_match_all('/!?\[\[([^\]|#\\\\]+)/', $canvasNode['text'], $matches);
						$sourceDir = dirname($cf['path']);
						foreach ($matches[1] as $target) {
							$target = trim($target, " \t\n\r\0\x0B\\");
							$result = $this->resolveTarget($target, $sourceDir, $byPath, $byBasename, $byPathLower, $byBasenameLower);
							if ($result !== null && $result['path'] !== $cf['path']) {
								$key = $cf['path'] . '>' . $result['path'];
								$edge = ['source' => $cf['path'], 'target' => $result['path']];
								if ($result['caseMismatch']) $edge['caseMismatch'] = true;
								$edgeSet[$key] = $edge;
							}
						}
					}
				}
			}

			foreach ($unresolvedNodes as $n) {
				$nodes[] = $n;
			}

			$tagNodes = [];
			foreach ($mdFiles as $f) {
				$tags = $this->extractTags($f['content']);
				foreach ($tags as $tag) {
					$tagId = 'tag:' . $tag;
					if (!isset($tagNodes[$tagId])) {
						$tagNodes[$tagId] = [
							'id' => $tagId,
							'name' => '#' . $tag,
							'type' => 'tag',
							'mtime' => 0,
						];
					}
					$key = $f['path'] . '>' . $tagId;
					$edgeSet[$key] = ['source' => $f['path'], 'target' => $tagId];
				}
			}

			foreach ($tagNodes as $n) {
				$nodes[] = $n;
			}

			return ['nodes' => $nodes, 'edges' => array_values($edgeSet)];
		} catch (\Exception $e) {
			return ['nodes' => [], 'edges' => []];
		}
	}

	private function collectVaultFiles(Folder $folder, string $prefix, array &$mdFiles, array &$canvasFiles): void {
		foreach ($folder->getDirectoryListing() as $node) {
			if (str_starts_with($node->getName(), '.')) {
				continue;
			}
			if ($node instanceof Folder) {
				$this->collectVaultFiles($node, $prefix . '/' . $node->getName(), $mdFiles, $canvasFiles);
			} else {
				$name = $node->getName();
				$path = $prefix . '/' . $name;
				try {
					if (str_ends_with($name, '.md')) {
						$mdFiles[] = ['path' => $path, 'content' => $node->getContent(), 'mtime' => $node->getMTime()];
					} elseif (str_ends_with($name, '.canvas')) {
						$canvasFiles[] = ['path' => $path, 'content' => $node->getContent(), 'mtime' => $node->getMTime()];
					}
				} catch (\Exception $e) {
					// skip unreadable files
				}
			}
		}
	}

	private function extractTags(string $content): array {
		$tags = [];

		if (preg_match('/^---\s*\n([\s\S]*?)\n---/m', $content, $fmMatch)) {
			$fm = $fmMatch[1];
			if (preg_match('/^tags:\s*\[([^\]]*)\]/m', $fm, $inlineMatch)) {
				foreach (explode(',', $inlineMatch[1]) as $t) {
					$t = trim($t, " \t\"'");
					if ($t !== '') $tags[] = $t;
				}
			} elseif (preg_match('/^tags:\s*\n((?:\s+-\s+.+\n?)+)/m', $fm, $listMatch)) {
				preg_match_all('/^\s+-\s+(.+)$/m', $listMatch[1], $items);
				foreach ($items[1] as $t) {
					$t = trim($t, " \t\"'");
					if ($t !== '') $tags[] = $t;
				}
			} elseif (preg_match('/^tags:\s+(.+)$/m', $fm, $singleMatch)) {
				$val = trim($singleMatch[1]);
				foreach (explode(',', $val) as $t) {
					$t = trim($t, " \t\"'");
					if ($t !== '') $tags[] = $t;
				}
			}
		}

		$cleaned = preg_replace('/```[\s\S]*?```/', '', $content);
		$cleaned = preg_replace('/`[^`]*`/', '', $cleaned);
		preg_match_all('/(?:^|\s)#([a-zA-Z][a-zA-Z0-9_\/-]*)/', $cleaned, $matches);
		foreach ($matches[1] as $t) {
			$tags[] = $t;
		}

		return array_values(array_unique($tags));
	}

	private function resolveTarget(string $target, string $sourceDir, array $byPath, array $byBasename, array $byPathLower = [], array $byBasenameLower = []): ?array {
		$candidates = [
			'/' . $target . '.md',
			'/' . $target,
		];
		if ($sourceDir !== '' && $sourceDir !== '/') {
			$candidates[] = $sourceDir . '/' . $target . '.md';
			$candidates[] = $sourceDir . '/' . $target;
		}
		foreach ($candidates as $c) {
			if (isset($byPath[$c])) {
				return ['path' => $c, 'caseMismatch' => false];
			}
		}
		if (isset($byBasename[$target]) && count($byBasename[$target]) === 1) {
			return ['path' => $byBasename[$target][0], 'caseMismatch' => false];
		}

		foreach ($candidates as $c) {
			$lower = strtolower($c);
			if (isset($byPathLower[$lower])) {
				return ['path' => $byPathLower[$lower], 'caseMismatch' => true];
			}
		}
		$targetLower = strtolower($target);
		if (isset($byBasenameLower[$targetLower]) && count($byBasenameLower[$targetLower]) === 1) {
			return ['path' => $byBasenameLower[$targetLower][0], 'caseMismatch' => true];
		}
		return null;
	}

	public function searchVault(string $vaultPath, string $query): array {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return [];
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$folder = $userFolder->get($relativePath);
			if (!($folder instanceof Folder)) {
				return [];
			}
			$mdFiles = [];
			$canvasFiles = [];
			$this->collectVaultFiles($folder, '', $mdFiles, $canvasFiles);

			$allFiles = array_merge($mdFiles, $canvasFiles);
			$tokens = $this->tokenizeQuery($query);
			$ast = $this->parseQuery($tokens);
			$terms = $this->collectQueryTerms($ast);

			$results = [];
			$limit = 50;

			foreach ($allFiles as $f) {
				if (count($results) >= $limit) break;

				$file = [
					'content' => $f['content'],
					'path' => $f['path'],
					'name' => preg_replace('/\.(md|canvas)$/', '', basename($f['path'])),
				];

				if (!$this->evaluateNode($ast, $file)) {
					continue;
				}

				$matchLines = [];
				if (!empty($terms)) {
					$lines = explode("\n", $f['content']);
					foreach ($lines as $i => $line) {
						foreach ($terms as $t) {
							if (stripos($line, $t) !== false) {
								$matchLines[] = ['line' => $i + 1, 'text' => mb_substr(trim($line), 0, 120)];
								break;
							}
						}
						if (count($matchLines) >= 5) break;
					}
				}

				$results[] = [
					'path' => $f['path'],
					'name' => $file['name'],
					'matches' => $matchLines,
				];
			}
			return $results;
		} catch (\Exception $e) {
			return [];
		}
	}

	// --- Expression parser (mirrors src/services/searchMatcher.js) ---

	private const QUERY_OPERATORS = [
		'task-todo:', 'task-done:', 'content:', 'section:',
		'file:', 'path:', 'line:', 'block:', 'task:', 'tag:',
	];

	private function tokenizeQuery(string $input): array {
		$tokens = [];
		$i = 0;
		$len = strlen($input);

		while ($i < $len) {
			if ($input[$i] === ' ' || $input[$i] === "\t") { $i++; continue; }

			if ($input[$i] === '"') {
				$j = $i + 1;
				$str = '';
				while ($j < $len && $input[$j] !== '"') {
					if ($input[$j] === '\\' && $j + 1 < $len) { $str .= $input[$j + 1]; $j += 2; }
					else { $str .= $input[$j]; $j++; }
				}
				$tokens[] = ['t' => 'Q', 'v' => $str];
				$i = $j + 1;
				continue;
			}

			if ($input[$i] === '/' && $i < $len - 1) {
				$j = $i + 1;
				$pat = '';
				while ($j < $len && $input[$j] !== '/') {
					if ($input[$j] === '\\' && $j + 1 < $len) { $pat .= $input[$j] . $input[$j + 1]; $j += 2; }
					else { $pat .= $input[$j]; $j++; }
				}
				if ($j < $len && $j > $i + 1) {
					$tokens[] = ['t' => 'R', 'v' => $pat];
					$i = $j + 1;
					continue;
				}
			}

			if ($input[$i] === '[') {
				$j = $i + 1;
				$depth = 1;
				$s = '';
				while ($j < $len && $depth > 0) {
					if ($input[$j] === '[') $depth++;
					elseif ($input[$j] === ']') { $depth--; if ($depth === 0) break; }
					$s .= $input[$j];
					$j++;
				}
				$pn = null;
				$pv = null;
				if (str_starts_with($s, '"')) {
					$eq = strpos($s, '"', 1);
					$pn = substr($s, 1, $eq - 1);
					$rest = trim(substr($s, $eq + 1));
					$pv = str_starts_with($rest, ':') ? trim(substr($rest, 1)) : null;
				} else {
					$ci = strpos($s, ':');
					if ($ci !== false) {
						$pn = trim(substr($s, 0, $ci));
						$pv = trim(substr($s, $ci + 1));
					} else {
						$pn = trim($s);
					}
				}
				$tokens[] = ['t' => 'P', 'n' => $pn, 'v' => $pv];
				$i = $j + 1;
				continue;
			}

			if ($input[$i] === '(') { $tokens[] = ['t' => '(']; $i++; continue; }
			if ($input[$i] === ')') { $tokens[] = ['t' => ')']; $i++; continue; }
			if ($input[$i] === '-') { $tokens[] = ['t' => '-']; $i++; continue; }

			$j = $i;
			while ($j < $len && $input[$j] !== ' ' && $input[$j] !== "\t" && $input[$j] !== '(' && $input[$j] !== ')' && $input[$j] !== '"') $j++;
			if ($j === $i) { $i++; continue; }
			$word = substr($input, $i, $j - $i);

			$matched = false;
			foreach (self::QUERY_OPERATORS as $op) {
				if (strlen($word) >= strlen($op) && strtolower(substr($word, 0, strlen($op))) === $op) {
					$rest = substr($word, strlen($op));
					if ($rest !== false && $rest !== '' && $rest[0] === '-') {
						$tokens[] = ['t' => 'O', 'n' => substr($op, 0, -1), 'v' => null];
						$tokens[] = ['t' => '-'];
						if (strlen($rest) > 1) $tokens[] = ['t' => 'W', 'v' => substr($rest, 1)];
					} else {
						$tokens[] = ['t' => 'O', 'n' => substr($op, 0, -1), 'v' => $rest ?: null];
					}
					$matched = true;
					break;
				}
			}
			if (!$matched) {
				if ($word === 'OR') $tokens[] = ['t' => 'OR'];
				else $tokens[] = ['t' => 'W', 'v' => $word];
			}
			$i = $j;
		}
		return $tokens;
	}

	private function parseQuery(array $tokens): array {
		$pos = 0;
		$peek = function() use (&$tokens, &$pos) { return $pos < count($tokens) ? $tokens[$pos] : null; };
		$advance = function() use (&$tokens, &$pos) { return $tokens[$pos++]; };

		$expr = null;
		$orExpr = null;
		$andExpr = null;
		$unary = null;
		$primary = null;

		$expr = function() use (&$orExpr) { return $orExpr(); };

		$orExpr = function() use (&$andExpr, &$peek, &$advance) {
			$left = $andExpr();
			while (($t = $peek()) !== null && $t['t'] === 'OR') {
				$advance();
				$left = ['t' => 'OR', 'l' => $left, 'r' => $andExpr()];
			}
			return $left;
		};

		$andExpr = function() use (&$unary, &$peek) {
			$terms = [$unary()];
			while (($t = $peek()) !== null && $t['t'] !== 'OR' && $t['t'] !== ')') {
				$terms[] = $unary();
			}
			return count($terms) === 1 ? $terms[0] : ['t' => 'AND', 'c' => $terms];
		};

		$unary = function() use (&$unary, &$primary, &$peek, &$advance) {
			if (($t = $peek()) !== null && $t['t'] === '-') {
				$advance();
				return ['t' => 'NOT', 'c' => $unary()];
			}
			return $primary();
		};

		$primary = function() use (&$expr, &$unary, &$primary, &$peek, &$advance) {
			$tok = $peek();
			if ($tok === null) return ['t' => 'W', 'v' => ''];

			if ($tok['t'] === '(') {
				$advance();
				$e = $expr();
				if (($t = $peek()) !== null && $t['t'] === ')') $advance();
				return $e;
			}

			if ($tok['t'] === 'O') {
				$advance();
				$child = null;
				if ($tok['v'] !== null) {
					$child = ['t' => 'W', 'v' => $tok['v']];
				} elseif (($t = $peek()) !== null && $t['t'] === '(') {
					$advance();
					$child = $expr();
					if (($t = $peek()) !== null && $t['t'] === ')') $advance();
				} elseif (($t = $peek()) !== null && $t['t'] === 'Q') {
					$child = $advance();
				} elseif (($t = $peek()) !== null && $t['t'] === 'R') {
					$child = $advance();
				} elseif (($t = $peek()) !== null && $t['t'] !== 'OR' && $t['t'] !== ')') {
					$child = $unary();
				} else {
					$child = ['t' => 'W', 'v' => ''];
				}
				return ['t' => 'OP', 'n' => $tok['n'], 'c' => $child];
			}

			if ($tok['t'] === 'P' || $tok['t'] === 'W' || $tok['t'] === 'Q' || $tok['t'] === 'R') {
				$advance();
				return $tok;
			}

			$advance();
			return ['t' => 'W', 'v' => ''];
		};

		return $expr();
	}

	private function evalNodeText(array $node, string $text): bool {
		switch ($node['t']) {
			case 'W': return stripos($text, $node['v']) !== false;
			case 'Q': return stripos($text, $node['v']) !== false;
			case 'R':
				try { return (bool)preg_match('/' . $node['v'] . '/i', $text); }
				catch (\Exception $e) { return false; }
			case 'AND':
				foreach ($node['c'] as $ch) { if (!$this->evalNodeText($ch, $text)) return false; }
				return true;
			case 'OR': return $this->evalNodeText($node['l'], $text) || $this->evalNodeText($node['r'], $text);
			case 'NOT': return !$this->evalNodeText($node['c'], $text);
			default: return false;
		}
	}

	private function evaluateNode(array $node, array $file): bool {
		$content = $file['content'] ?? '';

		switch ($node['t']) {
			case 'W': return stripos($content, $node['v']) !== false;
			case 'Q': return stripos($content, $node['v']) !== false;
			case 'R':
				try { return (bool)preg_match('/' . $node['v'] . '/i', $content); }
				catch (\Exception $e) { return false; }
			case 'AND':
				foreach ($node['c'] as $ch) { if (!$this->evaluateNode($ch, $file)) return false; }
				return true;
			case 'OR': return $this->evaluateNode($node['l'], $file) || $this->evaluateNode($node['r'], $file);
			case 'NOT': return !$this->evaluateNode($node['c'], $file);

			case 'OP':
				$c = $node['c'];
				switch ($node['n']) {
					case 'file': return $this->evalNodeText($c, $file['name'] ?? '');
					case 'path': return $this->evalNodeText($c, $file['path'] ?? '');
					case 'content': return $this->evalNodeText($c, $content);
					case 'tag':
						if (!$content) return false;
						$tags = $this->extractTags($content);
						foreach ($tags as $tag) { if ($this->evalNodeText($c, $tag)) return true; }
						return false;
					case 'line':
						foreach (explode("\n", $content) as $line) {
							if ($this->evalNodeText($c, $line)) return true;
						}
						return false;
					case 'section':
						foreach ($this->splitSections($content) as $sec) {
							if ($this->evalNodeText($c, $sec)) return true;
						}
						return false;
					case 'block':
						foreach ($this->splitBlocks($content) as $blk) {
							if ($this->evalNodeText($c, $blk)) return true;
						}
						return false;
					case 'task':
						foreach (explode("\n", $content) as $l) {
							if (preg_match('/^\s*- \[.\]/', $l) && $this->evalNodeText($c, $l)) return true;
						}
						return false;
					case 'task-todo':
						foreach (explode("\n", $content) as $l) {
							if (preg_match('/^\s*- \[ \]/', $l) && $this->evalNodeText($c, $l)) return true;
						}
						return false;
					case 'task-done':
						foreach (explode("\n", $content) as $l) {
							if (preg_match('/^\s*- \[x\]/i', $l) && $this->evalNodeText($c, $l)) return true;
						}
						return false;
					default: return false;
				}

			case 'P':
				if (!$content) return false;
				$fm = $this->parseFrontmatter($content);
				$pn = strtolower($node['n']);
				if (!isset($fm[$pn])) return false;
				if (!isset($node['v']) || $node['v'] === null) return true;
				if ($node['v'] === 'null') return trim($fm[$pn]) === '';
				return stripos($fm[$pn], $node['v']) !== false;

			default: return false;
		}
	}

	private function collectQueryTerms(array $node): array {
		$terms = [];
		if ($node['t'] === 'W' || $node['t'] === 'Q') {
			$terms[] = $node['v'];
		}
		if (isset($node['c'])) {
			if (is_array($node['c']) && isset($node['c'][0])) {
				foreach ($node['c'] as $ch) { $terms = array_merge($terms, $this->collectQueryTerms($ch)); }
			} elseif (is_array($node['c']) && isset($node['c']['t'])) {
				$terms = array_merge($terms, $this->collectQueryTerms($node['c']));
			}
		}
		if (isset($node['l'])) $terms = array_merge($terms, $this->collectQueryTerms($node['l']));
		if (isset($node['r'])) $terms = array_merge($terms, $this->collectQueryTerms($node['r']));
		return $terms;
	}

	private function splitSections(string $content): array {
		$sections = [];
		$cur = [];
		foreach (explode("\n", $content) as $line) {
			if (preg_match('/^#{1,6}\s+/', $line)) {
				if (!empty($cur)) $sections[] = implode("\n", $cur);
				$cur = [];
			}
			$cur[] = $line;
		}
		if (!empty($cur)) $sections[] = implode("\n", $cur);
		return $sections;
	}

	private function splitBlocks(string $content): array {
		return array_values(array_filter(
			preg_split('/\n\s*\n/', $content),
			fn($b) => trim($b) !== ''
		));
	}

	public function queryVault(string $vaultPath, array $fields, string $filter, ?string $sort, int $limit): array {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return ['fields' => $fields, 'rows' => []];
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$folder = $userFolder->get($relativePath);
			if (!($folder instanceof Folder)) {
				return ['fields' => $fields, 'rows' => []];
			}

			$mdFiles = [];
			$canvasFiles = [];
			$this->collectVaultFiles($folder, '', $mdFiles, $canvasFiles);
			$allFiles = array_merge($mdFiles, $canvasFiles);

			$ast = null;
			if ($filter !== '') {
				$tokens = $this->tokenizeQuery($filter);
				$ast = $this->parseQuery($tokens);
			}

			$rows = [];
			foreach ($allFiles as $f) {
				$fm = null;
				$tags = null;
				$links = null;

				if ($ast !== null) {
					$file = [
						'content' => $f['content'],
						'path' => $f['path'],
						'name' => preg_replace('/\.(md|canvas)$/', '', basename($f['path'])),
					];
					if (!$this->evaluateNode($ast, $file)) {
						continue;
					}
				}

				$row = [];
				foreach ($fields as $field) {
					$row[$field] = $this->extractField($f, $field, $fm, $tags, $links);
				}
				$rows[] = $row;
			}

			if ($sort !== null && $sort !== '') {
				$desc = str_starts_with($sort, '-');
				$sortField = $desc ? substr($sort, 1) : $sort;
				usort($rows, function ($a, $b) use ($sortField, $desc) {
					$va = $a[$sortField] ?? null;
					$vb = $b[$sortField] ?? null;
					if (is_array($va)) $va = implode(', ', $va);
					if (is_array($vb)) $vb = implode(', ', $vb);
					$cmp = strnatcasecmp((string)$va, (string)$vb);
					return $desc ? -$cmp : $cmp;
				});
			}

			if ($limit > 0 && count($rows) > $limit) {
				$rows = array_slice($rows, 0, $limit);
			}

			return ['fields' => $fields, 'rows' => $rows];
		} catch (\Exception $e) {
			return ['fields' => $fields, 'rows' => [], 'error' => $e->getMessage()];
		}
	}

	private function extractField(array $f, string $field, ?array &$fm, ?array &$tags, ?array &$links): mixed {
		switch ($field) {
			case 'file.name':
				return preg_replace('/\.(md|canvas)$/', '', basename($f['path']));
			case 'file.path':
				return $f['path'];
			case 'file.ext':
				return pathinfo($f['path'], PATHINFO_EXTENSION);
			case 'file.mtime':
				return $f['mtime'];
			case 'tags':
				if ($tags === null) $tags = $this->extractTags($f['content']);
				return $tags;
			case 'links':
				if ($links === null) $links = $this->extractLinks($f['content']);
				return $links;
			default:
				if ($fm === null) $fm = $this->parseFrontmatter($f['content']);
				return $fm[$field] ?? null;
		}
	}

	private function extractLinks(string $content): array {
		$linkContent = preg_replace('/```[\s\S]*?```/m', '', $content);
		$linkContent = preg_replace('/`[^`]+`/', '', $linkContent);
		preg_match_all('/!?\[\[([^\]|#\\\\]+)/', $linkContent, $matches);
		$links = [];
		foreach ($matches[1] as $target) {
			$target = trim($target, " \t\n\r\0\x0B\\");
			if ($target !== '') {
				$links[] = $target;
			}
		}
		return array_values(array_unique($links));
	}

	public function getVaultProperties(string $vaultPath): array {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return [];
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$folder = $userFolder->get($relativePath);
			if (!($folder instanceof Folder)) {
				return [];
			}
			$mdFiles = [];
			$canvasFiles = [];
			$this->collectVaultFiles($folder, '', $mdFiles, $canvasFiles);

			$props = [];
			foreach ($mdFiles as $f) {
				$fm = $this->parseFrontmatter($f['content']);
				foreach (array_keys($fm) as $key) {
					$props[$key] = true;
				}
			}
			$keys = array_keys($props);
			sort($keys);
			return $keys;
		} catch (\Exception $e) {
			return [];
		}
	}

	private function parseFrontmatter(string $content): array {
		if (!preg_match('/^---\s*\n([\s\S]*?)\n---/m', $content, $fmMatch)) {
			return [];
		}
		$fm = [];
		$lines = explode("\n", $fmMatch[1]);
		foreach ($lines as $line) {
			if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_-]*):\s*(.*)$/', $line, $m)) {
				$fm[$m[1]] = trim($m[2]);
			}
		}
		return $fm;
	}

	public function getFileInfo(string $vaultPath, string $name): ?array {
		$file = $this->findFileByName($vaultPath, $name);
		if ($file === null) {
			return null;
		}
		$userFolder = $this->getUserFolder();
		$filePath = $file->getPath();
		$userPath = $userFolder->getPath();
		$relativePath = substr($filePath, strlen($userPath));
		$dir = dirname($relativePath);
		return [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'mimeType' => $file->getMimeType(),
			'dir' => $dir,
		];
	}

	public function findFileByName(string $vaultPath, string $name): ?File {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return null;
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$folder = $userFolder->get($relativePath);
			if (!($folder instanceof Folder)) {
				return null;
			}
			return $this->searchFileRecursive($folder, $name);
		} catch (\Exception $e) {
			return null;
		}
	}

	private function searchFileRecursive(Folder $folder, string $name): ?File {
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof File && $node->getName() === $name) {
				return $node;
			}
			if ($node instanceof Folder && !str_starts_with($node->getName(), '.')) {
				$found = $this->searchFileRecursive($node, $name);
				if ($found !== null) {
					return $found;
				}
			}
		}
		return null;
	}

	public function getFileContent(string $vaultPath, string $filePath): ?string {
		$userFolder = $this->getUserFolder();
		if ($userFolder === null) {
			return null;
		}
		try {
			$relativePath = substr($vaultPath, strlen($userFolder->getPath()) + 1);
			$fullPath = $relativePath . $filePath;
			$file = $userFolder->get($fullPath);
			return $file->getContent();
		} catch (\Exception $e) {
			return null;
		}
	}

	private function getUserFolder(): ?Folder {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return null;
		}
		return $this->rootFolder->getUserFolder($user->getUID());
	}
}
