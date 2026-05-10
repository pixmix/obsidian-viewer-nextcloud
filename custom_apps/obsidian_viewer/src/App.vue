<template>
	<div id="content" class="app-obsidian_viewer" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
		<div class="action-bar">
			<div class="action-bar__top">
				<button class="action-bar__btn"
					:title="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'"
					@click="sidebarCollapsed = !sidebarCollapsed">
					<MenuIcon :size="20" />
				</button>
				<button class="action-bar__btn"
					title="Quick switcher (Ctrl+O)"
					@click="showQuickSwitcher = true">
					<MagnifyIcon :size="20" />
				</button>
				<button class="action-bar__btn"
					:class="{ 'action-bar__btn--active': showGraph }"
					title="Graph view"
					@click="toggleGraph">
					<GraphIcon :size="20" />
				</button>
				<button class="action-bar__btn"
					:class="{ 'action-bar__btn--active': showQuery }"
					title="Query view"
					@click="toggleQuery">
					<TableIcon :size="20" />
				</button>
			</div>
		</div>

		<NcAppNavigation>
			<template #list>
				<div class="sidebar-tabs">
					<button class="sidebar-tabs__btn"
						:class="{ 'sidebar-tabs__btn--active': sidebarTab === 'files' }"
						title="Files"
						@click="sidebarTab = 'files'">
						<FolderIcon :size="18" />
					</button>
					<button class="sidebar-tabs__btn"
						:class="{ 'sidebar-tabs__btn--active': sidebarTab === 'search' }"
						title="Search"
						@click="sidebarTab = 'search'">
						<MagnifyIcon :size="18" />
					</button>
					<button class="sidebar-tabs__btn"
						:class="{ 'sidebar-tabs__btn--active': sidebarTab === 'bookmarks' }"
						title="Bookmarks"
						@click="sidebarTab = 'bookmarks'">
						<BookmarkOutlineIcon :size="18" />
					</button>
				</div>

				<template v-if="sidebarTab === 'files'">
					<div v-if="vaults.length > 1" class="vault-selector">
						<NcSelect v-model="selectedVault"
							:options="vaults"
							label="name"
							track-by="name"
							placeholder="Select a vault..."
							@input="onVaultSelected" />
					</div>
					<FileTree v-if="fileTree.length > 0"
						:tree="fileTree"
						:selected-path="selectedFile"
						@select="navigateToFile" />
					<div v-else-if="loading" class="loading-indicator">
						<NcLoadingIcon />
					</div>
					<NcEmptyContent v-else-if="vaults.length === 0 && !loading"
						name="No vaults found"
						description="No Obsidian vaults were found in your files. A vault is a folder containing a .obsidian subdirectory.">
						<template #icon>
							<FolderIcon />
						</template>
					</NcEmptyContent>
				</template>

				<VaultSearch v-else-if="sidebarTab === 'search' && selectedVault"
					:vault-name="selectedVault.name"
					@select="onSearchSelect" />

				<Bookmarks v-else-if="sidebarTab === 'bookmarks' && selectedVault"
					ref="bookmarks"
					:vault-name="selectedVault.name"
					:selected-path="selectedFile"
					@select="navigateToFile" />
			</template>
		</NcAppNavigation>

		<NcAppContent>
			<div class="title-bar">
				<div class="title-bar__nav">
					<NcButton type="tertiary"
						:disabled="!canGoBack"
						aria-label="Back"
						@click="goBack">
						<template #icon>
							<ArrowLeftIcon :size="20" />
						</template>
					</NcButton>
					<NcButton type="tertiary"
						:disabled="!canGoForward"
						aria-label="Forward"
						@click="goForward">
						<template #icon>
							<ArrowRightIcon :size="20" />
						</template>
					</NcButton>
				</div>
				<span class="title-bar__title">{{ currentTitle }}</span>
				<div class="title-bar__actions">
					<NcButton v-if="showGraph"
						type="tertiary"
						:aria-label="showGraphSettings ? 'Close settings' : 'Graph settings'"
						@click="showGraphSettings = !showGraphSettings">
						<template #icon>
							<CogIcon :size="20" />
						</template>
					</NcButton>
					<NcButton v-if="selectedFile && !showGraph"
						type="tertiary"
						:aria-label="isCurrentBookmarked ? 'Remove bookmark' : 'Bookmark this file'"
						@click="toggleCurrentBookmark">
						<template #icon>
							<BookmarkIcon v-if="isCurrentBookmarked" :size="20" />
							<BookmarkOutlineIcon v-else :size="20" />
						</template>
					</NcButton>
					<NcActions type="tertiary">
						<template #icon>
							<DotsVerticalIcon :size="20" />
						</template>
						<NcActionButton @click="showProperties = !showProperties" :close-after-click="true">
							<template #icon>
								<TextBoxCheckIcon v-if="showProperties" :size="20" />
								<TextBoxOutlineIcon v-else :size="20" />
							</template>
							{{ showProperties ? 'Hide properties' : 'Show properties' }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>

			<div class="content-body">
				<QueryView v-if="showQuery && selectedVault"
					:vault-name="selectedVault.name"
					:properties="vaultProperties"
					@navigate="onQueryNavigate" />
				<template v-else-if="showGraph">
					<div class="graph-container">
						<GraphView v-if="graphData"
							ref="graphView"
							:graph-data="graphData"
							:current-file="selectedFile"
							:settings="graphSettings"
							@navigate="onGraphNodeClick" />
						<div v-else-if="graphLoading" class="loading-indicator">
							<NcLoadingIcon />
						</div>
						<GraphSettings v-if="showGraphSettings"
							:settings="graphSettings"
							:properties="vaultProperties"
							@update="onGraphSettingsUpdate"
							@close="showGraphSettings = false"
							@reset="resetGraphSettings"
							@animate="animateGraph" />
					</div>
				</template>
				<CanvasViewer v-else-if="fileContent !== null && isCanvasFile"
					:content="fileContent"
					:embed-resolver="resolveEmbed"
					@navigate="onWikiLinkNavigate" />
				<MarkdownViewer v-else-if="fileContent !== null"
					:content="fileContent"
					:file-name="selectedFile"
					:embed-resolver="resolveEmbed"
					:image-resolver="resolveImageUrl"
					:show-properties="showProperties"
					:file-url-resolver="resolveFileUrl"
					@navigate="onWikiLinkNavigate" />
				<NcEmptyContent v-else
					name="Select a note"
					description="Choose a markdown file from the sidebar to view it.">
					<template #icon>
						<FileDocumentIcon />
					</template>
				</NcEmptyContent>
			</div>
		</NcAppContent>

		<QuickSwitcher v-if="showQuickSwitcher"
			:files="allFiles"
			@select="onQuickSwitcherSelect"
			@close="showQuickSwitcher = false" />
	</div>
</template>

<script>
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import FileTree from './components/FileTree.vue'
import MarkdownViewer from './components/MarkdownViewer.vue'
import CanvasViewer from './components/CanvasViewer.vue'
import GraphView from './components/GraphView.vue'
import GraphSettings from './components/GraphSettings.vue'
import QuickSwitcher from './components/QuickSwitcher.vue'
import VaultSearch from './components/VaultSearch.vue'
import Bookmarks from './components/Bookmarks.vue'
import QueryView from './components/QueryView.vue'

import FolderIcon from 'vue-material-design-icons/Folder.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import GraphIcon from 'vue-material-design-icons/GraphOutline.vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import ArrowLeftIcon from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DotsVerticalIcon from 'vue-material-design-icons/DotsVertical.vue'
import BookmarkIcon from 'vue-material-design-icons/Bookmark.vue'
import BookmarkOutlineIcon from 'vue-material-design-icons/BookmarkOutline.vue'
import TableIcon from 'vue-material-design-icons/Table.vue'
import TextBoxOutlineIcon from 'vue-material-design-icons/TextBoxOutline.vue'
import TextBoxCheckIcon from 'vue-material-design-icons/TextBoxCheck.vue'

import { generateUrl } from '@nextcloud/router'
import { fetchVaults, fetchFiles, fetchFileContent, fetchGraph, fetchProperties, getRawFileUrl, fetchFileInfo } from './services/api.js'

const DEFAULT_GRAPH_SETTINGS = {
	filters: {
		search: '',
		tags: false,
		attachments: false,
		existingOnly: false,
		mdLinks: false,
		orphans: false,
	},
	groups: [],
	display: {
		arrows: false,
		textFadeThreshold: 0,
		nodeSize: 4,
		linkThickness: 1,
	},
	forces: {
		centerForce: 0.52,
		repelForce: 10,
		linkForce: 1,
		linkDistance: 250,
	},
}

export default {
	name: 'App',

	components: {
		NcAppNavigation,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcSelect,
		NcButton,
		NcActions,
		NcActionButton,
		FileTree,
		MarkdownViewer,
		CanvasViewer,
		GraphView,
		GraphSettings,
		QuickSwitcher,
		VaultSearch,
		Bookmarks,
		QueryView,
		FolderIcon,
		FileDocumentIcon,
		GraphIcon,
		MenuIcon,
		MagnifyIcon,
		ArrowLeftIcon,
		ArrowRightIcon,
		CogIcon,
		DotsVerticalIcon,
		BookmarkIcon,
		BookmarkOutlineIcon,
		TableIcon,
		TextBoxOutlineIcon,
		TextBoxCheckIcon,
	},

	data() {
		return {
			vaults: [],
			selectedVault: null,
			fileTree: [],
			selectedFile: null,
			fileContent: null,
			loading: true,
			showGraph: false,
			showQuery: false,
			showProperties: false,
			graphData: null,
			graphLoading: false,
			vaultProperties: [],
			sidebarCollapsed: false,
			sidebarTab: 'files',
			showQuickSwitcher: false,
			showGraphSettings: false,
			graphSettings: JSON.parse(JSON.stringify(DEFAULT_GRAPH_SETTINGS)),
			history: [],
			historyIndex: -1,
			bookmarkVersion: 0,
		}
	},

	computed: {
		currentTitle() {
			if (this.showQuery) return 'Query view'
			if (this.showGraph) return 'Graph view'
			if (this.selectedFile) {
				return this.selectedFile
					.substring(this.selectedFile.lastIndexOf('/') + 1)
					.replace(/\.(md|canvas)$/, '')
			}
			if (this.selectedVault) return this.selectedVault.name
			return ''
		},

		isCanvasFile() {
			return this.selectedFile && this.selectedFile.endsWith('.canvas')
		},

		canGoBack() {
			return this.historyIndex > 0
		},

		canGoForward() {
			return this.historyIndex < this.history.length - 1
		},

		allFiles() {
			return this.flattenTree(this.fileTree)
		},

		isCurrentBookmarked() {
			// Depends on bookmarkVersion for reactivity
			void this.bookmarkVersion
			if (!this.selectedFile || !this.selectedVault) return false
			try {
				const raw = localStorage.getItem('obsidian_viewer_bookmarks_' + this.selectedVault.name)
				const bookmarks = raw ? JSON.parse(raw) : []
				return bookmarks.some((b) => b.path === this.selectedFile)
			} catch {
				return false
			}
		},
	},

	async mounted() {
		try {
			this.vaults = await fetchVaults()
			if (this.vaults.length === 1) {
				this.selectedVault = this.vaults[0]
				await this.loadFileTree()
			}
		} catch (e) {
			console.error('Failed to load vaults:', e)
		} finally {
			this.loading = false
		}
		document.addEventListener('keydown', this.onKeyDown)
		this.$el.addEventListener('click', this.onGlobalClick)
	},

	beforeDestroy() {
		document.removeEventListener('keydown', this.onKeyDown)
		this.$el.removeEventListener('click', this.onGlobalClick)
	},

	methods: {
		onKeyDown(e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'o') {
				e.preventDefault()
				this.showQuickSwitcher = !this.showQuickSwitcher
			}
		},

		onGlobalClick(e) {
			const link = e.target.closest('a')
			if (!link) return
			if (link.target === '_blank') return
			if (link.classList.contains('obsidian-wikilink')) return

			const href = link.getAttribute('href')
			if (!href) return

			try {
				const url = new URL(href, window.location.href)
				const appBase = window.location.pathname.replace(/\/+$/, '')
				if (url.origin === window.location.origin && url.pathname.startsWith(appBase)) {
					e.preventDefault()
					e.stopPropagation()
					const path = decodeURIComponent(url.pathname.substring(appBase.length + 1))
					if (path) {
						const name = path.replace(/\.md$/, '')
						const resolved = this.resolveWikiLink(name)
						if (resolved) {
							this.navigateToFile(resolved)
						}
					}
				}
			} catch (err) {
				console.error('onGlobalClick error:', err)
			}
		},

		async onVaultSelected() {
			this.fileTree = []
			this.selectedFile = null
			this.fileContent = null
			this.graphData = null
			this.showGraph = false
			this.showGraphSettings = false
			this.history = []
			this.historyIndex = -1
			if (this.selectedVault) {
				await this.loadFileTree()
			}
		},

		async loadFileTree() {
			this.loading = true
			try {
				this.fileTree = await fetchFiles(this.selectedVault.name)
				fetchProperties(this.selectedVault.name).then((p) => { this.vaultProperties = p }).catch(() => {})
			} catch (e) {
				console.error('Failed to load file tree:', e)
			} finally {
				this.loading = false
			}
		},

		navigateToFile(path) {
			if (this.historyIndex < this.history.length - 1) {
				this.history = this.history.slice(0, this.historyIndex + 1)
			}
			this.history.push(path)
			this.historyIndex = this.history.length - 1
			this.showGraph = false
			this.showGraphSettings = false
			this.loadFile(path)
		},

		async loadFile(path) {
			if (!path.endsWith('.md') && !path.endsWith('.canvas')) {
				const fileName = path.substring(path.lastIndexOf('/') + 1)
				try {
					const info = await fetchFileInfo(this.selectedVault.name, fileName)
					const params = new URLSearchParams({ dir: info.dir, openfile: 'true' })
					const url = generateUrl('/apps/files/files/{id}', { id: info.id }) + '?' + params.toString()
					window.open(url, '_blank')
				} catch (e) {
					const dir = '/' + this.selectedVault.name + path.substring(0, path.lastIndexOf('/'))
					const url = generateUrl('/apps/files/?dir={dir}&scrollto={file}', { dir, file: fileName })
					window.open(url, '_blank')
				}
				return
			}
			this.selectedFile = path
			try {
				const content = await fetchFileContent(this.selectedVault.name, path)
				if (this.selectedFile === path) {
					this.fileContent = content
				}
			} catch (e) {
				console.error('loadFile failed for', path, e)
				if (this.selectedFile === path) {
					this.fileContent = null
				}
			}
		},

		goBack() {
			if (!this.canGoBack) return
			this.historyIndex--
			this.showGraph = false
			this.showGraphSettings = false
			this.loadFile(this.history[this.historyIndex])
		},

		goForward() {
			if (!this.canGoForward) return
			this.historyIndex++
			this.showGraph = false
			this.showGraphSettings = false
			this.loadFile(this.history[this.historyIndex])
		},

		toggleQuery() {
			if (!this.selectedVault) return
			this.showQuery = !this.showQuery
			if (this.showQuery) {
				this.showGraph = false
				this.showGraphSettings = false
			}
		},

		onQueryNavigate(path) {
			this.showQuery = false
			this.navigateToFile(path)
		},

		async toggleGraph() {
			if (!this.selectedVault) return
			this.showGraph = !this.showGraph
			if (this.showGraph) {
				this.showQuery = false
			}
			if (this.showGraph && !this.graphData) {
				await this.loadGraph()
			}
			if (!this.showGraph) {
				this.showGraphSettings = false
			}
		},

		async loadGraph() {
			this.graphLoading = true
			try {
				this.graphData = await fetchGraph(this.selectedVault.name, {
					mdLinks: this.graphSettings.filters.mdLinks,
				})
			} catch (e) {
				console.error('Failed to load graph:', e)
			} finally {
				this.graphLoading = false
			}
		},

		onGraphNodeClick(path) {
			this.navigateToFile(path)
		},

		onWikiLinkNavigate(target) {
			const resolved = this.resolveWikiLink(target)
			if (resolved) {
				this.navigateToFile(resolved)
			}
		},

		onSearchSelect(path) {
			this.navigateToFile(path)
		},

		toggleCurrentBookmark() {
			if (!this.selectedFile || !this.selectedVault) return
			const key = 'obsidian_viewer_bookmarks_' + this.selectedVault.name
			let bookmarks = []
			try {
				const raw = localStorage.getItem(key)
				bookmarks = raw ? JSON.parse(raw) : []
			} catch { /* empty */ }
			const idx = bookmarks.findIndex((b) => b.path === this.selectedFile)
			if (idx >= 0) {
				bookmarks.splice(idx, 1)
			} else {
				const name = this.selectedFile.substring(this.selectedFile.lastIndexOf('/') + 1).replace(/\.(md|canvas)$/, '')
				bookmarks.push({ path: this.selectedFile, name, addedAt: Date.now() })
			}
			localStorage.setItem(key, JSON.stringify(bookmarks))
			this.bookmarkVersion++
			if (this.$refs.bookmarks) {
				this.$refs.bookmarks.loadBookmarks()
			}
		},

		onQuickSwitcherSelect(path) {
			this.showQuickSwitcher = false
			this.navigateToFile(path)
		},

		onGraphSettingsUpdate(settings) {
			const mdLinksChanged = settings.filters.mdLinks !== this.graphSettings.filters.mdLinks
			this.graphSettings = settings
			if (mdLinksChanged && this.showGraph && this.selectedVault) {
				this.loadGraph()
			}
		},

		resetGraphSettings() {
			this.graphSettings = JSON.parse(JSON.stringify(DEFAULT_GRAPH_SETTINGS))
		},

		animateGraph() {
			if (this.$refs.graphView) {
				this.$refs.graphView.animate()
			}
		},

		resolveWikiLink(target) {
			const candidates = [
				'/' + target + '.md',
				'/' + target,
			]

			if (this.selectedFile) {
				const dir = this.selectedFile.substring(0, this.selectedFile.lastIndexOf('/'))
				candidates.push(dir + '/' + target + '.md')
				candidates.push(dir + '/' + target)
			}

			const allPaths = this.flattenTree(this.fileTree)
			for (const candidate of candidates) {
				if (allPaths.includes(candidate)) {
					return candidate
				}
			}
			for (const path of allPaths) {
				const fileName = path.substring(path.lastIndexOf('/') + 1).replace(/\.md$/, '')
				if (fileName === target) {
					return path
				}
			}

			const targetLower = target.toLowerCase()
			const candidatesLower = candidates.map((c) => c.toLowerCase())
			for (const path of allPaths) {
				const pathLower = path.toLowerCase()
				for (const cl of candidatesLower) {
					if (pathLower === cl) return path
				}
			}
			for (const path of allPaths) {
				const fileName = path.substring(path.lastIndexOf('/') + 1).replace(/\.md$/, '')
				if (fileName.toLowerCase() === targetLower) return path
			}

			return null
		},

		async resolveEmbed(target) {
			if (!this.selectedVault) return null

			const hashIdx = target.indexOf('#')
			const filePart = hashIdx >= 0 ? target.slice(0, hashIdx) : target
			const anchor = hashIdx >= 0 ? target.slice(hashIdx + 1) : null

			if (!filePart) return null

			const path = this.resolveWikiLink(filePart)
			if (!path) return null
			try {
				const content = await fetchFileContent(this.selectedVault.name, path)
				if (content === null) return null
				if (!anchor) return content
				return this.extractSection(content, anchor)
			} catch (e) {
				console.error('resolveEmbed fetch failed:', path, e)
				return null
			}
		},

		extractSection(content, anchor) {
			const lines = content.split('\n')

			if (anchor.startsWith('^')) {
				const blockId = anchor.slice(1)
				const re = new RegExp('\\^' + blockId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$')
				for (const line of lines) {
					if (re.test(line)) {
						return line.replace(re, '').trim()
					}
				}
				return null
			}

			const targetHeading = anchor.toLowerCase().trim()
			let startIdx = -1
			let startLevel = 0
			for (let i = 0; i < lines.length; i++) {
				const m = lines[i].match(/^(#{1,6})\s+(.+)/)
				if (!m) continue
				if (startIdx === -1) {
					if (m[2].trim().toLowerCase() === targetHeading) {
						startIdx = i
						startLevel = m[1].length
					}
				} else {
					if (m[1].length <= startLevel) {
						return lines.slice(startIdx, i).join('\n')
					}
				}
			}
			if (startIdx >= 0) return lines.slice(startIdx).join('\n')
			return null
		},

		async resolveFileUrl(fileName) {
			if (!this.selectedVault) return null
			try {
				const info = await fetchFileInfo(this.selectedVault.name, fileName)
				const params = new URLSearchParams({ dir: info.dir, openfile: 'true' })
				return {
					openUrl: generateUrl('/apps/files/files/{id}', { id: info.id }) + '?' + params.toString(),
					rawUrl: getRawFileUrl(this.selectedVault.name, fileName),
				}
			} catch (e) {
				return null
			}
		},

		resolveImageUrl(fileName) {
			if (!this.selectedVault) return null
			return getRawFileUrl(this.selectedVault.name, fileName)
		},

		flattenTree(tree) {
			const paths = []
			for (const node of tree) {
				if (node.type === 'file') {
					paths.push(node.path)
				} else if (node.children) {
					paths.push(...this.flattenTree(node.children))
				}
			}
			return paths
		},
	},
}
</script>

<style>
#content.app-obsidian_viewer {
	background: var(--color-main-background) !important;
	overflow: hidden !important;
}

#content.app-obsidian_viewer .app-content {
	display: flex !important;
	flex-direction: column !important;
	overflow: hidden !important;
}

#content.app-obsidian_viewer.sidebar-collapsed .app-navigation {
	display: none !important;
}

#content.app-obsidian_viewer .app-navigation-toggle {
	display: none !important;
}
</style>

<style scoped>
.action-bar {
	width: 44px;
	min-width: 44px;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-top: 8px;
	gap: 4px;
	background: var(--color-main-background);
	border-right: 1px solid var(--color-border);
	z-index: 1500;
	order: -1;
}

.action-bar__top {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}

.action-bar__btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 36px;
	height: 36px;
	border: none;
	border-radius: 8px;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	opacity: 0.6;
	transition: opacity 0.15s, background 0.15s;
}

.action-bar__btn:hover {
	background: var(--color-background-hover);
	opacity: 1;
}

.action-bar__btn--active {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	opacity: 1;
}

.title-bar {
	display: flex;
	align-items: center;
	height: 44px;
	min-height: 44px;
	padding: 0 4px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.title-bar__nav {
	display: flex;
}

.title-bar__title {
	flex: 1;
	text-align: center;
	font-weight: 600;
	font-size: 14px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	padding: 0 8px;
}

.title-bar__actions {
	display: flex;
}

.content-body {
	flex: 1;
	overflow-y: auto;
	position: relative;
}

.graph-container {
	height: 100%;
	position: relative;
}

.sidebar-tabs {
	display: flex;
	align-items: center;
	gap: 2px;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.sidebar-tabs__btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: none;
	border-radius: 6px;
	background: transparent;
	color: var(--color-main-text);
	cursor: pointer;
	opacity: 0.5;
	transition: opacity 0.15s, background 0.15s;
}

.sidebar-tabs__btn:hover {
	background: var(--color-background-hover);
	opacity: 0.8;
}

.sidebar-tabs__btn--active {
	opacity: 1;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
}

.vault-selector {
	padding: 8px 12px;
	border-bottom: 1px solid var(--color-border);
}

.loading-indicator {
	display: flex;
	justify-content: center;
	padding: 20px;
}
</style>
