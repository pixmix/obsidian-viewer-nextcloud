<template>
	<div class="bookmarks">
		<div class="bookmarks__header">
			<BookmarkIcon :size="16" />
			<span>Bookmarks</span>
		</div>

		<div v-if="bookmarks.length === 0" class="bookmarks__empty">
			No bookmarks found
		</div>

		<div v-else class="bookmarks__list">
			<div v-for="bm in bookmarks"
				:key="bm.path"
				class="bookmarks__item"
				:class="{ 'bookmarks__item--active': bm.path === selectedPath }"
				@click="$emit('select', bm.path)">
				<FileDocumentIcon :size="16" class="bookmarks__item-icon" />
				<span class="bookmarks__item-name">{{ bm.name }}</span>
				<button class="bookmarks__item-remove"
					title="Remove bookmark"
					@click.stop="removeBookmark(bm.path)">
					<CloseIcon :size="14" />
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import BookmarkIcon from 'vue-material-design-icons/Bookmark.vue'
import FileDocumentIcon from 'vue-material-design-icons/FileDocument.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

const STORAGE_PREFIX = 'obsidian_viewer_bookmarks_'

export default {
	name: 'Bookmarks',

	components: { BookmarkIcon, FileDocumentIcon, CloseIcon },

	props: {
		vaultName: { type: String, required: true },
		selectedPath: { type: String, default: null },
	},

	emits: ['select'],

	data() {
		return {
			bookmarks: [],
		}
	},

	watch: {
		vaultName: {
			immediate: true,
			handler() {
				this.loadBookmarks()
			},
		},
	},

	methods: {
		storageKey() {
			return STORAGE_PREFIX + this.vaultName
		},

		loadBookmarks() {
			try {
				const raw = localStorage.getItem(this.storageKey())
				this.bookmarks = raw ? JSON.parse(raw) : []
			} catch {
				this.bookmarks = []
			}
		},

		saveBookmarks() {
			localStorage.setItem(this.storageKey(), JSON.stringify(this.bookmarks))
		},

		addBookmark(path) {
			if (this.bookmarks.some((b) => b.path === path)) return
			const name = path.substring(path.lastIndexOf('/') + 1).replace(/\.(md|canvas)$/, '')
			this.bookmarks.push({ path, name, addedAt: Date.now() })
			this.saveBookmarks()
		},

		removeBookmark(path) {
			this.bookmarks = this.bookmarks.filter((b) => b.path !== path)
			this.saveBookmarks()
		},

		isBookmarked(path) {
			return this.bookmarks.some((b) => b.path === path)
		},

		toggleBookmark(path) {
			if (this.isBookmarked(path)) {
				this.removeBookmark(path)
			} else {
				this.addBookmark(path)
			}
		},
	},
}
</script>

<style scoped>
.bookmarks {
	padding: 8px;
}

.bookmarks__header {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 8px 4px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--color-text-maxcontrast);
}

.bookmarks__empty {
	padding: 40px 20px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.bookmarks__list {
	display: flex;
	flex-direction: column;
	gap: 1px;
}

.bookmarks__item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 8px;
	border-radius: 6px;
	cursor: pointer;
	font-size: 13px;
}

.bookmarks__item:hover {
	background: var(--color-background-hover);
}

.bookmarks__item--active {
	background: var(--color-primary-element-light);
}

.bookmarks__item-icon {
	flex-shrink: 0;
	opacity: 0.6;
}

.bookmarks__item-name {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.bookmarks__item-remove {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	border: none;
	background: transparent;
	cursor: pointer;
	opacity: 0;
	color: var(--color-main-text);
	border-radius: 4px;
	flex-shrink: 0;
}

.bookmarks__item:hover .bookmarks__item-remove {
	opacity: 0.5;
}

.bookmarks__item-remove:hover {
	opacity: 1 !important;
	background: var(--color-background-hover);
}
</style>
